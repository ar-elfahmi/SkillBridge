<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\AnalysisStatus;
use App\Enums\CompetencyReviewStatus;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\Analysis;
use App\Models\Career;
use App\Models\CourseSkill;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use App\Modules\Ai\GeminiClient;
use App\Modules\Ai\Services\RateLimitRetry;
use App\Modules\Matching\Jobs\RunAnalysisJob;
use App\Modules\Matching\Services\CandidateFinder;
use App\Modules\Matching\Services\MatchJudge;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use App\Modules\Rps\Jobs\ExtractRpsMetadataJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GeminiLimitHandlingTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/test-flash:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'skillbridge.gemini.mode' => 'gemini',
            'skillbridge.gemini.api_key' => 'fake-test-key',
            'skillbridge.gemini.flash_model' => 'test-flash',
            'skillbridge.gemini.timeout_seconds' => 30,
            'skillbridge.gemini.thinking_level' => '',
            'skillbridge.matching.sim_floor' => 0.50,
            'skillbridge.matching.top_k' => 3,
            'skillbridge.gemini.jobs.max_rate_limit_waits' => 2,
        ]);

        Http::preventStrayRequests();
        Sleep::fake();
    }

    public function test_rate_limit_retry_waits_then_returns_the_result(): void
    {
        $calls = 0;

        $result = RateLimitRetry::run(function () use (&$calls): string {
            if (++$calls < 3) {
                throw new GeminiRateLimitedException(5);
            }

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(3, $calls);
        Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(5)->seconds()]);
    }

    public function test_rate_limit_retry_gives_up_after_the_configured_waits(): void
    {
        $calls = 0;

        $this->expectException(GeminiRateLimitedException::class);

        try {
            RateLimitRetry::run(function () use (&$calls): void {
                $calls++;

                throw new GeminiRateLimitedException(3);
            });
        } finally {
            $this->assertSame(3, $calls);
        }
    }

    public function test_analysis_waits_for_the_minute_budget_and_still_completes(): void
    {
        [$analysis, $courseSkill] = $this->analysisFixture();
        Http::fake([self::URL => Http::sequence()
            ->push('Too many', 429, ['Retry-After' => '7'])
            ->push($this->judgeResponse($courseSkill->id))]);

        $this->runAnalysis($analysis);

        $analysis->refresh();
        $this->assertSame(AnalysisStatus::Completed, $analysis->status);
        $this->assertSame(1, $analysis->summary['covered']);
        Sleep::assertSequence([Sleep::for(7)->seconds()]);
    }

    public function test_analysis_fails_with_a_quota_message_instead_of_saving_missing_results(): void
    {
        [$analysis] = $this->analysisFixture();
        Http::fake([self::URL => Http::response(['error' => ['details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel']]]]]], 429)]);

        $this->runAnalysis($analysis);

        $analysis->refresh();
        $this->assertSame(AnalysisStatus::Failed, $analysis->status);
        $this->assertSame('quota_exhausted', $analysis->error_code);
        $this->assertStringContainsString('Kuota AI harian habis', (string) $analysis->error_message);
        $this->assertSame(0, $analysis->skillMatches()->count());
        $this->assertNull($analysis->summary);
    }

    public function test_analysis_is_released_when_the_minute_budget_never_frees_up(): void
    {
        [$analysis] = $this->analysisFixture();
        Http::fake([self::URL => Http::response('Too many', 429, ['Retry-After' => '3'])]);

        $job = new RunAnalysisJob($analysis->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(CandidateFinder::class), app(MatchJudge::class));

        $job->assertReleased(3);
        $analysis->refresh();
        $this->assertSame(AnalysisStatus::Processing, $analysis->status);
        $this->assertSame(0, $analysis->skillMatches()->count());
        Http::assertSentCount(3);
    }

    public function test_job_settings_use_explicit_timeouts_and_a_time_bounded_retry_window(): void
    {
        config([
            'skillbridge.gemini.jobs.timeout_seconds' => 90,
            'skillbridge.gemini.jobs.long_timeout_seconds' => 240,
            'skillbridge.gemini.jobs.max_exceptions' => 2,
            'skillbridge.gemini.jobs.retry_until_minutes' => 10,
        ]);

        $short = new ExtractRpsMetadataJob(1);
        $long = new RunAnalysisJob(1);

        $this->assertSame(90, $short->timeout);
        $this->assertSame(240, $long->timeout);
        $this->assertSame(2, $long->maxExceptions);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->getTimestamp(), $short->retryUntil()->getTimestamp(), 2);
    }

    public function test_metadata_job_is_released_when_rate_limited_and_keeps_the_draft_empty(): void
    {
        $document = $this->parsedDocument();
        Http::fake([self::URL => Http::response('Too many', 429, ['Retry-After' => '11'])]);

        $job = new ExtractRpsMetadataJob($document->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(GeminiClient::class));

        $job->assertReleased(11);
        $document->refresh();
        $this->assertNull($document->detected_metadata);
        $this->assertNull($document->error_message);
    }

    public function test_metadata_job_reports_the_daily_quota_and_allows_manual_entry(): void
    {
        $document = $this->parsedDocument();
        Http::fake([self::URL => Http::response(['error' => ['details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel']]]]]], 429)]);

        $job = new ExtractRpsMetadataJob($document->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(GeminiClient::class));

        $document->refresh();
        $this->assertStringContainsString('Kuota AI harian habis', (string) $document->error_message);
        $this->assertStringContainsString('melengkapi metadata secara manual', (string) $document->error_message);
        $this->assertSame('quota_exhausted', $document->error_code);
        $job->assertNotReleased();
    }

    private function parsedDocument(): RpsDocument
    {
        $document = RpsDocument::factory()->create(['parse_status' => ParseStatus::Parsed]);
        RpsPage::factory()->create([
            'rps_document_id' => $document->id,
            'page_number' => 1,
            'text' => 'RPS Pemrograman Web kode IF202 3 SKS Semester 4 Teknik Informatika',
        ]);

        return $document;
    }

    private function runAnalysis(Analysis $analysis): void
    {
        $job = new RunAnalysisJob($analysis->id);
        $job->withFakeQueueInteractions();
        $job->handle(app(CandidateFinder::class), app(MatchJudge::class));
    }

    /**
     * One RPS competency and one job requirement that pass the similarity floor.
     *
     * @return array{0: Analysis, 1: CourseSkill}
     */
    private function analysisFixture(): array
    {
        $user = User::factory()->create();
        $career = Career::factory()->create(['name' => 'Backend Developer']);
        $rps = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Confirmed,
        ]);
        $skill = Skill::factory()->create(['canonical_name' => 'PHP', 'category' => SkillCategory::Language]);

        $courseSkill = CourseSkill::factory()->create([
            'rps_document_id' => $rps->id,
            'skill_id' => $skill->id,
            'competency' => 'Mampu membuat aplikasi web dengan PHP modern',
            'evidence' => 'Praktikum PHP lanjut',
            'verification_status' => VerificationStatus::Verified,
            'review_status' => CompetencyReviewStatus::Pending,
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $posting = JobPosting::factory()->create(['career_id' => $career->id, 'extraction_status' => ExtractionStatus::Completed]);
        JobSkill::factory()->create([
            'job_posting_id' => $posting->id,
            'skill_id' => $skill->id,
            'skill' => 'PHP',
            'requirement_text' => 'Menguasai PHP 8',
            'evidence' => 'PHP 8',
            'embedding' => [0.95, 0.05, 0.0],
        ]);

        $analysis = Analysis::factory()->create([
            'user_id' => $user->id,
            'rps_document_id' => $rps->id,
            'career_id' => $career->id,
            'status' => AnalysisStatus::Queued,
        ]);
        $analysis->jobPostings()->sync([$posting->id]);

        return [$analysis, $courseSkill];
    }

    /**
     * @return array<string, mixed>
     */
    private function judgeResponse(int $courseSkillId): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode([
            'classification' => 'covered',
            'matched_course_skill_id' => $courseSkillId,
            'reason' => 'Mahasiswa mempelajari PHP modern secara lengkap.',
            'evidence' => 'Praktikum PHP lanjut',
        ], JSON_THROW_ON_ERROR)]]]]]];
    }
}
