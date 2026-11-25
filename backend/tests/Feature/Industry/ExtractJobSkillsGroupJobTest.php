<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Models\Career;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Modules\Ai\EmbeddingService;
use App\Modules\Ai\GeminiClient;
use App\Modules\Ai\Services\GeminiQuota;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Industry\Jobs\ExtractJobSkillsGroupJob;
use App\Modules\Industry\Jobs\ExtractJobSkillsJob;
use App\Modules\Industry\Services\JobExtractionDispatcher;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ExtractJobSkillsGroupJobTest extends TestCase
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
            'skillbridge.gemini.jobs.concurrent_postings' => 2,
        ]);

        Http::preventStrayRequests();
        Sleep::fake();
    }

    public function test_two_postings_are_extracted_with_two_requests_and_one_embedding_batch(): void
    {
        [$first, $second] = $this->postings(2);
        $this->fakeGemini();

        $this->runGroup([$first->id, $second->id]);

        $this->assertSame(ExtractionStatus::Completed, $first->refresh()->extraction_status);
        $this->assertSame(ExtractionStatus::Completed, $second->refresh()->extraction_status);
        $this->assertSame(2, $this->requestsTo(self::URL));
        $this->assertSame(1, $this->requestsTo(self::EMBED_URL));
        $this->assertSame(1, JobSkill::where('job_posting_id', $first->id)->count());
        $this->assertSame(1, JobSkill::where('job_posting_id', $second->id)->count());
        $this->assertSame(0, JobSkill::whereNull('embedding')->count());
    }

    public function test_each_posting_is_sent_in_its_own_prompt(): void
    {
        [$first, $second] = $this->postings(2);
        $this->fakeGemini();

        $this->runGroup([$first->id, $second->id]);

        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL
            && str_contains($request['contents'][0]['parts'][0]['text'], 'POSTING-'.$first->id)
            && ! str_contains($request['contents'][0]['parts'][0]['text'], 'POSTING-'.$second->id));
    }

    public function test_an_unusable_response_hands_only_that_posting_to_the_single_job(): void
    {
        Queue::fake();
        [$first, $second] = $this->postings(2);
        $this->fakeGemini(fn (string $prompt): ?string => str_contains($prompt, 'POSTING-'.$second->id) ? 'not json at all' : null);

        $this->runGroup([$first->id, $second->id]);

        $this->assertSame(ExtractionStatus::Completed, $first->refresh()->extraction_status);
        $this->assertSame(ExtractionStatus::Processing, $second->refresh()->extraction_status);
        Queue::assertPushed(ExtractJobSkillsJob::class, 1);
        Queue::assertPushed(ExtractJobSkillsJob::class, fn (ExtractJobSkillsJob $job): bool => $job->jobPostingId === $second->id);
    }

    public function test_a_rate_limited_posting_is_retried_later_by_the_single_job(): void
    {
        Queue::fake();
        [$first, $second] = $this->postings(2);
        Http::fake([
            self::URL => function (Request $request) use ($second) {
                $prompt = $request['contents'][0]['parts'][0]['text'];

                return str_contains($prompt, 'POSTING-'.$second->id)
                    ? Http::response('Too many', 429, ['Retry-After' => '30'])
                    : Http::response($this->skillResponse($prompt));
            },
            self::EMBED_URL => $this->embedResponse(),
        ]);

        $this->runGroup([$first->id, $second->id]);

        $this->assertSame(ExtractionStatus::Completed, $first->refresh()->extraction_status);
        Queue::assertPushed(ExtractJobSkillsJob::class, fn (ExtractJobSkillsJob $job): bool => $job->jobPostingId === $second->id && $job->delay !== null);
    }

    public function test_daily_quota_marks_the_postings_failed_without_retrying(): void
    {
        Queue::fake();
        [$first, $second] = $this->postings(2);
        Http::fake([self::URL => Http::response(['error' => ['details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel']]]]]], 429)]);

        $this->runGroup([$first->id, $second->id]);

        $this->assertSame(ExtractionStatus::Failed, $first->refresh()->extraction_status);
        $this->assertSame(ExtractionStatus::Failed, $second->refresh()->extraction_status);
        $this->assertSame('quota_exhausted', $first->error_code);
        $this->assertStringContainsString('Kuota AI harian habis', (string) $second->error_message);
        Queue::assertNothingPushed();
    }

    public function test_an_interrupted_embedding_step_is_finished_by_the_single_job_without_gemini_text_calls(): void
    {
        [$posting] = $this->postings(1);
        JobSkill::factory()->create([
            'job_posting_id' => $posting->id,
            'embedding' => null,
            'embedding_model' => null,
            'embedding_dimensions' => null,
            'text_hash' => null,
        ]);
        Http::fake([self::EMBED_URL => $this->embedResponse()]);

        (new ExtractJobSkillsJob($posting->id))->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
            app(EmbeddingService::class),
        );

        $this->assertSame(ExtractionStatus::Completed, $posting->refresh()->extraction_status);
        $this->assertSame(0, $this->requestsTo(self::URL));
        $this->assertSame(0, JobSkill::whereNull('embedding')->count());
    }

    public function test_single_job_is_released_when_the_minute_budget_is_full(): void
    {
        [$posting] = $this->postings(1);
        Http::fake([self::URL => Http::response('Too many', 429, ['Retry-After' => '8'])]);

        $job = new ExtractJobSkillsJob($posting->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(GeminiClient::class), app(SkillNormalizer::class), app(EvidenceVerifier::class), app(EmbeddingService::class));

        $job->assertReleased(8);
        $this->assertSame(ExtractionStatus::Processing, $posting->refresh()->extraction_status);
    }

    public function test_single_job_fails_without_retry_when_the_daily_quota_is_gone(): void
    {
        [$posting] = $this->postings(1);
        Http::fake([self::URL => Http::response(['error' => ['details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel']]]]]], 429)]);

        $job = new ExtractJobSkillsJob($posting->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(GeminiClient::class), app(SkillNormalizer::class), app(EvidenceVerifier::class), app(EmbeddingService::class));

        $job->assertNotReleased();
        $this->assertSame(ExtractionStatus::Failed, $posting->refresh()->extraction_status);
        $this->assertSame('quota_exhausted', $posting->error_code);
    }

    public function test_dispatcher_groups_postings_in_pairs_and_leaves_a_single_one_alone(): void
    {
        Queue::fake();

        $result = app(JobExtractionDispatcher::class)->dispatch([1, 2, 3, 4, 5]);

        $this->assertSame(5, $result['scheduled']);
        $this->assertSame(0, $result['postponed']);
        Queue::assertPushed(ExtractJobSkillsGroupJob::class, 2);
        Queue::assertPushed(ExtractJobSkillsJob::class, 1);
        Queue::assertPushed(ExtractJobSkillsGroupJob::class, fn (ExtractJobSkillsGroupJob $job): bool => $job->jobPostingIds === [1, 2]);
        Queue::assertPushed(ExtractJobSkillsJob::class, fn (ExtractJobSkillsJob $job): bool => $job->jobPostingId === 5);
    }

    public function test_dispatcher_keeps_the_reserved_budget_and_postpones_the_rest(): void
    {
        Queue::fake();
        config([
            'skillbridge.gemini.limits.enabled' => true,
            'skillbridge.gemini.limits.redis_prefix' => 'test-dispatch:'.bin2hex(random_bytes(6)),
            'skillbridge.gemini.limits.flash.rpm' => 5,
            'skillbridge.gemini.limits.flash.rpd' => 10,
            'skillbridge.gemini.limits.embedding.rpm' => 5,
            'skillbridge.gemini.limits.embedding.rpd' => 50,
            'skillbridge.gemini.jobs.reserved_daily_requests' => 6,
        ]);
        $quota = new GeminiQuota;

        try {
            $result = app(JobExtractionDispatcher::class)->dispatch([1, 2, 3, 4, 5, 6]);

            // 10 requests per day, 6 reserved for users: only 4 postings may be scheduled.
            $this->assertSame(4, $result['scheduled']);
            $this->assertSame(2, $result['postponed']);
            $this->assertSame(4, $result['budget']);
            Queue::assertPushed(ExtractJobSkillsGroupJob::class, 2);
        } finally {
            $quota->reset(GeminiQuota::BUCKET_FLASH);
        }
    }

    public function test_pending_command_reports_postponed_postings_when_the_budget_is_short(): void
    {
        Queue::fake();
        config([
            'skillbridge.gemini.limits.enabled' => true,
            'skillbridge.gemini.limits.redis_prefix' => 'test-dispatch:'.bin2hex(random_bytes(6)),
            'skillbridge.gemini.limits.flash.rpm' => 5,
            'skillbridge.gemini.limits.flash.rpd' => 2,
            'skillbridge.gemini.limits.embedding.rpm' => 5,
            'skillbridge.gemini.limits.embedding.rpd' => 50,
            'skillbridge.gemini.jobs.reserved_daily_requests' => 0,
        ]);
        $quota = new GeminiQuota;
        $this->postings(3);

        try {
            $this->artisan('skillbridge:extract-pending-jobs', ['--limit' => 10])
                ->expectsOutputToContain('Dijadwalkan ekstraksi skill untuk 2 lowongan.')
                ->expectsOutputToContain('1 lowongan ditunda karena anggaran kuota AI harian')
                ->assertSuccessful();
        } finally {
            $quota->reset(GeminiQuota::BUCKET_FLASH);
        }

        Queue::assertPushed(ExtractJobSkillsGroupJob::class, 1);
    }

    /**
     * @param  list<int>  $ids
     */
    private function runGroup(array $ids): void
    {
        (new ExtractJobSkillsGroupJob($ids))->handle(
            app(GeminiClient::class),
            app(SkillNormalizer::class),
            app(EvidenceVerifier::class),
            app(EmbeddingService::class),
        );
    }

    /**
     * @return list<JobPosting>
     */
    private function postings(int $count): array
    {
        $career = Career::factory()->create();
        $postings = [];

        for ($i = 0; $i < $count; $i++) {
            $posting = JobPosting::factory()->create([
                'career_id' => $career->id,
                'extraction_status' => ExtractionStatus::Pending,
                'description' => 'We need a developer. Requirement for this role: Laravel experience.',
                'qualifications' => '',
            ]);
            $posting->update(['description' => 'POSTING-'.$posting->id.' needs solid Laravel experience for building APIs.']);
            $postings[] = $posting->refresh();
        }

        return $postings;
    }

    /**
     * @param  (callable(string): ?string)|null  $replaceBodyFor  return a raw body to replace the answer for a prompt
     */
    private function fakeGemini(?callable $replaceBodyFor = null): void
    {
        Http::fake([
            self::URL => function (Request $request) use ($replaceBodyFor) {
                $prompt = $request['contents'][0]['parts'][0]['text'];
                $raw = $replaceBodyFor !== null ? $replaceBodyFor($prompt) : null;

                return $raw !== null
                    ? Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $raw]]]]]])
                    : Http::response($this->skillResponse($prompt));
            },
            self::EMBED_URL => $this->embedResponse(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function skillResponse(string $prompt): array
    {
        preg_match('/POSTING-(\d+)/', $prompt, $matches);
        $id = $matches[1] ?? '0';

        $payload = ['skills' => [[
            'skill' => 'Laravel',
            'category' => 'framework',
            'requirement_text' => "Solid Laravel experience for building APIs ({$id})",
            'evidence' => 'needs solid Laravel experience for building APIs',
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

    private function requestsTo(string $url): int
    {
        return Http::recorded(fn (Request $request): bool => $request->url() === $url)->count();
    }
}
