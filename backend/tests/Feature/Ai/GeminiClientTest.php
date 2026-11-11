<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\AiRun;
use App\Models\RpsDocument;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/test-flash:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'skillbridge.gemini.api_key' => 'fake-test-key',
            'skillbridge.gemini.flash_model' => 'test-flash',
            'skillbridge.gemini.timeout_seconds' => 17,
        ]);
        Http::preventStrayRequests();
        Sleep::fake();
    }

    public function test_sends_configured_request_and_records_safe_audit_with_subject(): void
    {
        $document = RpsDocument::factory()->create();
        $schema = ['type' => 'OBJECT', 'properties' => ['name' => ['type' => 'STRING']]];
        $options = [];
        $body = $this->success();
        $body['usageMetadata'] = ['promptTokenCount' => 12, 'candidatesTokenCount' => 3, 'totalTokenCount' => 15, 'unexpected' => 'private provider data'];
        Http::fake([self::URL => function (Request $request, array $requestOptions) use (&$options, $body) {
            $options = $requestOptions;

            return Http::response($body);
        }]);

        $result = (new GeminiClient)->generateContent('rps_metadata', 'Synthetic input', 'metadata-v1', $document, $schema);

        $this->assertSame($body, $result);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL
            && $request->method() === 'POST'
            && $request->hasHeader('x-goog-api-key', 'fake-test-key')
            && $request['contents'][0]['parts'][0]['text'] === 'Synthetic input'
            && $request['generationConfig'] === ['responseMimeType' => 'application/json', 'responseSchema' => $schema]);
        $this->assertSame(17, $options['timeout']);
        $this->assertSame(10, $options['connect_timeout']);
        $this->assertFalse($options['allow_redirects']);
        $run = AiRun::sole();
        $this->assertSame('rps_metadata', $run->purpose);
        $this->assertSame('test-flash', $run->model);
        $this->assertSame('metadata-v1', $run->prompt_version);
        $this->assertSame('success', $run->status);
        $this->assertNull($run->error);
        $this->assertGreaterThanOrEqual(0, $run->latency_ms);
        $this->assertTrue($run->subject->is($document));
        $this->assertEquals(['promptTokenCount' => 12, 'candidatesTokenCount' => 3, 'totalTokenCount' => 15], $run->usage);
        $request = Http::recorded()[0][0];
        $this->assertSame(hash('sha256', json_encode($request->data(), JSON_THROW_ON_ERROR)), $run->input_hash);
        $this->assertStringNotContainsString('Synthetic input', $run->toJson());
        $this->assertStringNotContainsString('fake-test-key', $run->toJson());
        $this->assertStringNotContainsString('private provider data', $run->toJson());
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_optional_fields_are_null_and_hash_tracks_request_input(): void
    {
        Http::fake([self::URL => Http::response($this->success())]);
        $client = new GeminiClient;
        $client->generateContent('rps_metadata', 'Input A');
        $client->generateContent('rps_metadata', 'Input A');
        $client->generateContent('rps_metadata', 'Input B');

        $runs = AiRun::orderBy('id')->get();
        $this->assertSame($runs[0]->input_hash, $runs[1]->input_hash);
        $this->assertNotSame($runs[0]->input_hash, $runs[2]->input_hash);
        $this->assertNull($runs[0]->prompt_version);
        $this->assertNull($runs[0]->subject);
        $this->assertNull($runs[0]->usage);
    }

    #[DataProvider('transientStatuses')]
    public function test_retries_transient_http_errors_and_records_each_attempt(int $status): void
    {
        Http::fake([self::URL => Http::sequence()->push(['error' => ['message' => 'Sensitive upstream message']], $status)->push($this->success())]);

        (new GeminiClient)->generateContent('match_judge', 'Synthetic input', 'judge-v1');

        $runs = AiRun::orderBy('id')->get();
        $this->assertSame(['failed', 'success'], $runs->pluck('status')->all());
        $this->assertSame('http_'.$status, $runs[0]->error);
        $this->assertSame($runs[0]->input_hash, $runs[1]->input_hash);
        Sleep::assertSequence([Sleep::for(1)->second()]);
        Http::assertSentCount(2);
    }

    public static function transientStatuses(): array
    {
        return [[408], [429], [500], [503], [504]];
    }

    public function test_stops_after_three_failed_http_attempts_with_safe_error(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['message' => 'fake-test-key Synthetic input']], 429)]);

        $this->assertSafeFailure();

        Http::assertSentCount(3);
        $this->assertSame(['http_429', 'http_429', 'http_429'], AiRun::orderBy('id')->pluck('error')->all());
        Sleep::assertSequence([Sleep::for(1)->second(), Sleep::for(2)->seconds()]);
    }

    public function test_connection_timeout_is_retried_and_can_recover(): void
    {
        $attempts = 0;
        Http::fake([self::URL => function () use (&$attempts) {
            return ++$attempts === 1 ? Http::failedConnection('cURL error 28: fake-test-key Synthetic input') : Http::response($this->success());
        }]);

        (new GeminiClient)->generateContent('rps_metadata', 'Synthetic input');

        $this->assertSame(2, $attempts);
        $this->assertSame(['connection_error', null], AiRun::orderBy('id')->pluck('error')->all());
        Sleep::assertSequence([Sleep::for(1)->second()]);
    }

    public function test_connection_failure_exhausts_retries_without_leaking_exception(): void
    {
        $attempts = 0;
        Http::fake([self::URL => function () use (&$attempts) {
            $attempts++;

            return Http::failedConnection('cURL error 28: fake-test-key Synthetic input');
        }]);

        $this->assertSafeFailure();

        $this->assertSame(3, $attempts);
        $this->assertSame(['connection_error', 'connection_error', 'connection_error'], AiRun::orderBy('id')->pluck('error')->all());
        Sleep::assertSequence([Sleep::for(1)->second(), Sleep::for(2)->seconds()]);
    }

    #[DataProvider('permanentStatuses')]
    public function test_does_not_retry_permanent_errors_or_redirects(int $status): void
    {
        Http::fake([self::URL => Http::response('Sensitive upstream message', $status, ['Location' => 'https://example.com'])]);

        $this->assertSafeFailure();

        Http::assertSentCount(1);
        $this->assertSame('http_'.$status, AiRun::sole()->error);
        Sleep::assertNeverSlept();
    }

    public static function permanentStatuses(): array
    {
        return [[400], [401], [403], [404], [302]];
    }

    #[DataProvider('invalidResponses')]
    public function test_rejects_invalid_blocked_or_incomplete_success_responses(mixed $body, string $error): void
    {
        Http::fake([self::URL => Http::response($body)]);

        $this->assertSafeFailure();

        $this->assertSame($error, AiRun::sole()->error);
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public static function invalidResponses(): array
    {
        return [
            ['invalid JSON', 'invalid_response'],
            [[], 'invalid_response'],
            [['promptFeedback' => ['blockReason' => 'SAFETY']], 'blocked_response'],
            [['candidates' => [['finishReason' => 'MAX_TOKENS']]], 'incomplete_response'],
            [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => ' ']]]]]], 'invalid_response'],
            [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'Internal thought', 'thought' => true]]]]]], 'invalid_response'],
        ];
    }

    #[DataProvider('invalidConfiguration')]
    public function test_invalid_configuration_sends_nothing_and_creates_no_audit(string $key, mixed $value): void
    {
        config(['skillbridge.gemini.'.$key => $value]);
        Http::fake();

        $this->assertSafeFailure();

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_runs', 0);
    }

    public static function invalidConfiguration(): array
    {
        return [['api_key', null], ['flash_model', ''], ['flash_model', '../other?key=private'], ['timeout_seconds', 0]];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_sends_nothing_and_creates_no_audit(string $purpose, string $prompt, ?string $version): void
    {
        Http::fake();

        try {
            (new GeminiClient)->generateContent($purpose, $prompt, $version);
            $this->fail('Expected invalid input to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Parameter panggilan AI tidak valid.', $exception->getMessage());
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_runs', 0);
    }

    public static function invalidInputs(): array
    {
        return [['unknown', 'Input', null], ['rps_metadata', ' ', null], ['rps_metadata', str_repeat('x', 100_001), null], ['rps_metadata', 'Input', 'invalid/version'], ['rps_metadata', "\xFF", null]];
    }

    private function assertSafeFailure(): void
    {
        try {
            (new GeminiClient)->generateContent('rps_metadata', 'Synthetic input', 'metadata-v1');
            $this->fail('Expected a safe Gemini failure.');
        } catch (GeminiException $exception) {
            $this->assertSame('Layanan AI gagal memproses permintaan. Silakan coba lagi.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('fake-test-key', $exception->getTraceAsString());
            $this->assertStringNotContainsString('Synthetic input', $exception->getTraceAsString());
        }

        foreach (AiRun::all() as $run) {
            $this->assertSame('failed', $run->status);
            $this->assertStringNotContainsString('fake-test-key', $run->toJson());
            $this->assertStringNotContainsString('Synthetic input', $run->toJson());
            $this->assertStringNotContainsString('Sensitive upstream message', $run->toJson());
        }
    }

    private function success(): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"name":"Example"}']]]]]];
    }
}
