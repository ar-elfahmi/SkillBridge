<?php

declare(strict_types=1);

namespace Tests\Feature\Competency;

use App\Enums\CompetencyOrigin;
use App\Models\CourseSkill;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Modules\Ai\GeminiClient;
use App\Modules\Competency\Jobs\ExtractCompetenciesJob;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ExtractCompetenciesParallelTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/test-flash:generateContent';

    private const EMBED_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'skillbridge.gemini.mode' => 'gemini',
            'skillbridge.gemini.api_key' => 'fake-test-key',
            'skillbridge.gemini.flash_model' => 'test-flash',
            'skillbridge.gemini.embedding_model' => 'gemini-embedding-001',
            'skillbridge.gemini.embedding_dimensions' => 768,
            'skillbridge.gemini.timeout_seconds' => 30,
            'skillbridge.gemini.thinking_level' => '',
            'skillbridge.gemini.max_concurrent' => 3,
            'skillbridge.gemini.chunk_retries' => 2,
            'skillbridge.gemini.jobs.max_rate_limit_waits' => 2,
        ]);

        Http::preventStrayRequests();
        Sleep::fake();
    }

    public function test_all_chunks_are_sent_together_and_merged_in_chunk_order(): void
    {
        $document = $this->documentWithPages(31); // 3 chunks: pages 1-15, 16-30, 31
        $this->fakeGemini();

        $this->runJob($document);

        $document->refresh();
        $this->assertSame(ExtractionStatus::Completed, $document->extraction_status);
        $this->assertSame(3, $this->flashRequests());

        $pages = CourseSkill::where('rps_document_id', $document->id)->orderBy('id')->pluck('page')->all();
        $this->assertSame([1, 16, 31], $pages);
    }

    public function test_only_the_failed_chunk_is_sent_again(): void
    {
        $document = $this->documentWithPages(31);
        $failures = 0;
        $this->fakeGemini(function (string $prompt) use (&$failures): ?int {
            if (str_contains($prompt, '[[PAGE 16]]') && $failures++ === 0) {
                return 500;
            }

            return null;
        });

        $this->runJob($document);

        $this->assertSame(ExtractionStatus::Completed, $document->refresh()->extraction_status);
        $this->assertSame(4, $this->flashRequests());
        $this->assertSame(1, $this->requestsContaining('[[PAGE 1]]'));
        $this->assertSame(2, $this->requestsContaining('[[PAGE 16]]'));
        $this->assertSame(1, $this->requestsContaining('[[PAGE 31]]'));
        Sleep::assertSequence([Sleep::for(1)->second()]);
        $this->assertSame(3, CourseSkill::where('rps_document_id', $document->id)->count());
    }

    public function test_a_chunk_that_keeps_failing_fails_the_job_and_keeps_existing_competencies(): void
    {
        $document = $this->documentWithPages(31);
        $existing = CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'origin' => CompetencyOrigin::Ai,
            'embedding' => array_fill(0, 768, 0.1),
        ]);
        $this->fakeGemini(fn (string $prompt): ?int => str_contains($prompt, '[[PAGE 16]]') ? 500 : null);

        $this->runJob($document);

        $document->refresh();
        $this->assertSame(ExtractionStatus::Failed, $document->extraction_status);
        $this->assertSame('Gagal mengekstrak kompetensi dari dokumen RPS. Silakan coba beberapa saat lagi.', $document->error_message);
        // 1 first round + 2 retries of the broken chunk, the two good chunks are sent once.
        $this->assertSame(5, $this->flashRequests());
        $this->assertTrue(CourseSkill::whereKey($existing->id)->exists());
    }

    public function test_a_rate_limited_chunk_waits_and_is_sent_again_without_failing(): void
    {
        $document = $this->documentWithPages(31);
        $limited = 0;
        Http::fake([
            self::URL => function (Request $request) use (&$limited) {
                $prompt = $request['contents'][0]['parts'][0]['text'];

                if (str_contains($prompt, '[[PAGE 31]]') && $limited++ === 0) {
                    return Http::response('Too many', 429, ['Retry-After' => '4']);
                }

                return Http::response($this->chunkResponse($prompt));
            },
            self::EMBED_URL => $this->embedResponse(),
        ]);

        $this->runJob($document);

        $this->assertSame(ExtractionStatus::Completed, $document->refresh()->extraction_status);
        $this->assertSame(4, $this->flashRequests());
        Sleep::assertSequence([Sleep::for(4)->seconds()]);
    }

    public function test_a_chunk_rate_limited_again_and_again_releases_the_job(): void
    {
        $document = $this->documentWithPages(16); // 2 chunks
        Http::fake([self::URL => Http::response('Too many', 429, ['Retry-After' => '6'])]);

        $job = new ExtractCompetenciesJob($document->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(GeminiClient::class), app(SkillNormalizer::class), app(EvidenceVerifier::class));

        $job->assertReleased(6);
        $document->refresh();
        $this->assertSame(ExtractionStatus::Processing, $document->extraction_status);
        $this->assertNull($document->error_message);
    }

    public function test_daily_quota_fails_the_job_with_a_clear_message_and_does_not_retry(): void
    {
        $document = $this->documentWithPages(16);
        Http::fake([self::URL => Http::response(['error' => ['details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel']]]]]], 429)]);

        $job = new ExtractCompetenciesJob($document->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(GeminiClient::class), app(SkillNormalizer::class), app(EvidenceVerifier::class));

        $job->assertNotReleased();
        $document->refresh();
        $this->assertSame(ExtractionStatus::Failed, $document->extraction_status);
        $this->assertStringContainsString('Kuota AI harian habis', (string) $document->error_message);
        $this->assertSame(2, $this->flashRequests());
    }

    public function test_resumes_with_embeddings_only_when_competencies_were_saved_without_vectors(): void
    {
        $document = $this->documentWithPages(31);
        CourseSkill::factory()->create([
            'rps_document_id' => $document->id,
            'origin' => CompetencyOrigin::Ai,
            'embedding' => null,
            'embedding_model' => null,
            'embedding_dimensions' => null,
            'text_hash' => null,
        ]);
        Http::fake([self::EMBED_URL => $this->embedResponse()]);

        $this->runJob($document);

        $this->assertSame(ExtractionStatus::Completed, $document->refresh()->extraction_status);
        $this->assertSame(0, $this->flashRequests());
        $this->assertNotNull(CourseSkill::where('rps_document_id', $document->id)->first()->embedding);
    }

    private function runJob(RpsDocument $document): void
    {
        $job = new ExtractCompetenciesJob($document->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(GeminiClient::class), app(SkillNormalizer::class), app(EvidenceVerifier::class));
    }

    private function documentWithPages(int $count): RpsDocument
    {
        $document = RpsDocument::factory()->create([
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Processing,
        ]);

        for ($page = 1; $page <= $count; $page++) {
            RpsPage::factory()->create([
                'rps_document_id' => $document->id,
                'page_number' => $page,
                'text' => $this->pageText($page),
            ]);
        }

        return $document;
    }

    private function pageText(int $page): string
    {
        return "Halaman {$page}: Mahasiswa mampu membangun layanan dengan Laravel unit {$page}.";
    }

    /**
     * @param  (callable(string): ?int)|null  $failWith  returns an HTTP status to fail that prompt, or null
     */
    private function fakeGemini(?callable $failWith = null): void
    {
        Http::fake([
            self::URL => function (Request $request) use ($failWith) {
                $prompt = $request['contents'][0]['parts'][0]['text'];
                $status = $failWith !== null ? $failWith($prompt) : null;

                return $status !== null ? Http::response(['error' => 'down'], $status) : Http::response($this->chunkResponse($prompt));
            },
            self::EMBED_URL => $this->embedResponse(),
        ]);
    }

    /**
     * One competency per chunk, taken from the first page that appears in the prompt.
     *
     * @return array<string, mixed>
     */
    private function chunkResponse(string $prompt): array
    {
        preg_match('/\[\[PAGE (\d+)\]\]/', $prompt, $matches);
        $page = (int) $matches[1];

        $payload = ['course' => 'Pemrograman Web', 'competencies' => [[
            'skill' => 'Laravel',
            'category' => 'framework',
            'competency' => "Mampu membangun layanan dengan Laravel unit {$page}",
            'evidence' => "Mahasiswa mampu membangun layanan dengan Laravel unit {$page}.",
            'source_type' => 'CPMK',
            'source_reference' => "CPMK-{$page}",
            'week' => null,
            'page' => $page,
        ]]];

        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($payload, JSON_THROW_ON_ERROR)]]]]]];
    }

    private function embedResponse(): callable
    {
        return fn (Request $request) => Http::response(['embeddings' => array_map(
            fn (): array => ['values' => array_fill(0, 768, 0.5)],
            $request['requests'],
        )]);
    }

    private function flashRequests(): int
    {
        return Http::recorded(fn (Request $request): bool => $request->url() === self::URL)->count();
    }

    private function requestsContaining(string $needle): int
    {
        return Http::recorded(fn (Request $request): bool => $request->url() === self::URL
            && str_contains($request['contents'][0]['parts'][0]['text'], $needle))->count();
    }
}
