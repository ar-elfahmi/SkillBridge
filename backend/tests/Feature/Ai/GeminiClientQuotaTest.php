<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\AiRun;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use App\Modules\Ai\GeminiClient;
use App\Modules\Ai\Services\GeminiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GeminiClientQuotaTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/test-flash:generateContent';

    private GeminiQuota $quota;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'skillbridge.gemini.mode' => 'gemini',
            'skillbridge.gemini.api_key' => 'fake-test-key',
            'skillbridge.gemini.flash_model' => 'test-flash',
            'skillbridge.gemini.timeout_seconds' => 17,
            'skillbridge.gemini.thinking_level' => '',
            'skillbridge.gemini.limits.enabled' => true,
            'skillbridge.gemini.limits.redis_prefix' => 'test-client-quota:'.bin2hex(random_bytes(6)),
            'skillbridge.gemini.limits.flash.rpm' => 5,
            'skillbridge.gemini.limits.flash.rpd' => 50,
        ]);

        Http::preventStrayRequests();
        Sleep::fake();
        $this->quota = new GeminiQuota;
    }

    protected function tearDown(): void
    {
        $this->quota->reset(GeminiQuota::BUCKET_FLASH);

        parent::tearDown();
    }

    public function test_sends_the_configured_thinking_level_for_gemini_3_models(): void
    {
        config(['skillbridge.gemini.thinking_level' => 'minimal']);
        Http::fake([self::URL => Http::response($this->success())]);

        (new GeminiClient)->generateContent('rps_metadata', 'Synthetic input', 'metadata-v1', null, ['type' => 'OBJECT']);

        Http::assertSent(fn (Request $request): bool => $request['generationConfig']['thinkingConfig'] === ['thinkingLevel' => 'MINIMAL']
            && $request['generationConfig']['responseMimeType'] === 'application/json');
    }

    public function test_omits_thinking_when_unset_or_not_a_known_level(): void
    {
        Http::fake([self::URL => Http::response($this->success())]);
        $client = new GeminiClient;

        config(['skillbridge.gemini.thinking_level' => '']);
        $client->generateContent('rps_metadata', 'Input A');
        config(['skillbridge.gemini.thinking_level' => 'ultra']);
        $client->generateContent('rps_metadata', 'Input B');

        Http::assertSent(fn (Request $request): bool => ! isset($request['generationConfig']));
        Http::assertSentCount(2);
    }

    public function test_every_http_attempt_reserves_one_request(): void
    {
        Http::fake([self::URL => Http::sequence()->push(['error' => 'down'], 503)->push($this->success())]);

        (new GeminiClient)->generateContent('match_judge', 'Synthetic input');

        $this->assertSame(2, $this->quota->snapshot(GeminiQuota::BUCKET_FLASH)['rpd_used']);
        $this->assertSame(2, AiRun::count());
    }

    public function test_minute_budget_exhausted_sends_nothing_and_records_no_run(): void
    {
        config(['skillbridge.gemini.limits.flash.rpm' => 1]);
        Http::fake([self::URL => Http::response($this->success())]);
        $client = new GeminiClient;
        $client->generateContent('rps_metadata', 'First');

        try {
            $client->generateContent('rps_metadata', 'Second');
            $this->fail('Expected a rate limit exception.');
        } catch (GeminiRateLimitedException $exception) {
            $this->assertGreaterThanOrEqual(1, $exception->retryAfterSeconds);
            $this->assertLessThanOrEqual(60, $exception->retryAfterSeconds);
        }

        Http::assertSentCount(1);
        $this->assertSame(1, AiRun::count());
    }

    public function test_daily_budget_exhausted_throws_quota_exception_without_sending(): void
    {
        config(['skillbridge.gemini.limits.flash.rpd' => 1]);
        Http::fake([self::URL => Http::response($this->success())]);
        $client = new GeminiClient;
        $client->generateContent('rps_metadata', 'First');

        $this->expectException(GeminiQuotaExhaustedException::class);

        try {
            $client->generateContent('rps_metadata', 'Second');
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_provider_429_is_not_retried_and_uses_the_retry_after_header(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['message' => 'secret detail']], 429, ['Retry-After' => '17'])]);

        try {
            (new GeminiClient)->generateContent('rps_metadata', 'Synthetic input');
            $this->fail('Expected a rate limit exception.');
        } catch (GeminiRateLimitedException $exception) {
            $this->assertSame(17, $exception->retryAfterSeconds);
            $this->assertStringNotContainsString('secret detail', $exception->getMessage());
        }

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
        $this->assertSame(['http_429'], AiRun::pluck('error')->all());
    }

    public function test_provider_429_reads_retry_delay_from_the_error_details(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['details' => [['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '34.2s']]]], 429)]);

        $this->expectException(GeminiRateLimitedException::class);
        $this->expectExceptionMessage('Batas permintaan AI per menit tercapai');

        try {
            (new GeminiClient)->generateContent('rps_metadata', 'Synthetic input');
        } catch (GeminiRateLimitedException $exception) {
            $this->assertSame(35, $exception->retryAfterSeconds);

            throw $exception;
        }
    }

    public function test_provider_429_without_hints_waits_a_full_minute(): void
    {
        Http::fake([self::URL => Http::response('Too many requests', 429)]);

        try {
            (new GeminiClient)->generateContent('rps_metadata', 'Synthetic input');
            $this->fail('Expected a rate limit exception.');
        } catch (GeminiRateLimitedException $exception) {
            $this->assertSame(60, $exception->retryAfterSeconds);
        }
    }

    public function test_provider_429_for_a_daily_quota_becomes_a_quota_exception(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]]]], 429)]);

        $this->expectException(GeminiQuotaExhaustedException::class);

        (new GeminiClient)->generateContent('rps_metadata', 'Synthetic input');
    }

    public function test_fallback_mode_uses_the_local_engine_when_the_budget_is_gone(): void
    {
        config(['skillbridge.gemini.mode' => 'fallback', 'skillbridge.gemini.limits.flash.rpd' => 1]);
        Http::fake([self::URL => Http::response($this->success())]);
        $client = new GeminiClient;
        $client->generateContent('rps_metadata', 'First');

        $result = $client->generateContent('rps_metadata', 'Second');

        $this->assertArrayHasKey('candidates', $result);
        Http::assertSentCount(1);
        $this->assertSame(['test-flash', 'offline-local-v1'], AiRun::orderBy('id')->pluck('model')->all());
    }

    public function test_fallback_mode_uses_the_local_engine_on_provider_429(): void
    {
        config(['skillbridge.gemini.mode' => 'fallback']);
        Http::fake([self::URL => Http::response('Too many requests', 429)]);

        $result = (new GeminiClient)->generateContent('rps_metadata', 'Synthetic input');

        $this->assertArrayHasKey('candidates', $result);
        Http::assertSentCount(1);
        $this->assertSame(['test-flash', 'offline-local-v1'], AiRun::orderBy('id')->pluck('model')->all());
    }

    public function test_concurrent_requests_return_results_per_id_and_reserve_once_for_the_group(): void
    {
        Http::fake([self::URL => Http::response($this->success())]);

        $results = (new GeminiClient)->generateContentConcurrent('rps_extraction', [
            'chunk-1' => ['prompt' => 'Chunk one', 'prompt_version' => 'rps-extract-v1'],
            'chunk-2' => ['prompt' => 'Chunk two', 'prompt_version' => 'rps-extract-v1'],
            'chunk-3' => ['prompt' => 'Chunk three', 'prompt_version' => 'rps-extract-v1'],
        ], 3);

        $this->assertSame(['chunk-1', 'chunk-2', 'chunk-3'], array_keys($results));
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('candidates', $result);
        }
        Http::assertSentCount(3);
        $this->assertSame(3, $this->quota->snapshot(GeminiQuota::BUCKET_FLASH)['rpd_used']);
        $this->assertSame(3, AiRun::where('purpose', 'rps_extraction')->where('status', 'success')->count());
    }

    public function test_concurrent_requests_report_only_the_failed_ids(): void
    {
        Http::fake([self::URL => fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'BROKEN')
            ? Http::response(['error' => 'down'], 500)
            : Http::response($this->success())]);

        $results = (new GeminiClient)->generateContentConcurrent('rps_extraction', [
            'ok' => ['prompt' => 'Fine chunk'],
            'bad' => ['prompt' => 'BROKEN chunk'],
        ]);

        $this->assertIsArray($results['ok']);
        $this->assertInstanceOf(GeminiException::class, $results['bad']);
        $this->assertSame(['http_500'], AiRun::where('status', 'failed')->pluck('error')->all());
    }

    public function test_concurrent_requests_surface_a_provider_429_as_rate_limited_for_that_id(): void
    {
        Http::fake([self::URL => fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'LIMITED')
            ? Http::response('Too many', 429, ['Retry-After' => '9'])
            : Http::response($this->success())]);

        $results = (new GeminiClient)->generateContentConcurrent('rps_extraction', [
            'ok' => ['prompt' => 'Fine chunk'],
            'slow' => ['prompt' => 'LIMITED chunk'],
        ]);

        $this->assertIsArray($results['ok']);
        $this->assertInstanceOf(GeminiRateLimitedException::class, $results['slow']);
        $this->assertSame(9, $results['slow']->retryAfterSeconds);
    }

    public function test_concurrent_requests_split_into_groups_that_fit_the_minute_budget(): void
    {
        config(['skillbridge.gemini.limits.flash.rpm' => 2]);
        Http::fake([self::URL => Http::response($this->success())]);

        $results = (new GeminiClient)->generateContentConcurrent('rps_extraction', [
            'a' => ['prompt' => 'A'],
            'b' => ['prompt' => 'B'],
            'c' => ['prompt' => 'C'],
        ], 3);

        $this->assertIsArray($results['a']);
        $this->assertIsArray($results['b']);
        $this->assertInstanceOf(GeminiRateLimitedException::class, $results['c']);
        Http::assertSentCount(2);
    }

    public function test_concurrent_requests_send_nothing_when_the_daily_budget_is_gone(): void
    {
        config(['skillbridge.gemini.limits.flash.rpd' => 1]);
        Http::fake();

        $results = (new GeminiClient)->generateContentConcurrent('rps_extraction', [
            'a' => ['prompt' => 'A'],
            'b' => ['prompt' => 'B'],
        ]);

        $this->assertInstanceOf(GeminiQuotaExhaustedException::class, $results['a']);
        $this->assertInstanceOf(GeminiQuotaExhaustedException::class, $results['b']);
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
    }

    public function test_concurrent_requests_run_locally_in_offline_mode(): void
    {
        config(['skillbridge.gemini.mode' => 'offline']);
        Http::fake();

        $results = (new GeminiClient)->generateContentConcurrent('rps_extraction', ['a' => ['prompt' => 'A']]);

        $this->assertIsArray($results['a']);
        Http::assertNothingSent();
        $this->assertSame(['offline-local-v1'], AiRun::pluck('model')->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function success(): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"ok":true}']]]]]];
    }
}
