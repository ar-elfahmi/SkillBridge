<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\Analysis;
use App\Models\Career;
use App\Models\Course;
use App\Models\JobPosting;
use App\Models\RpsDocument;
use App\Models\User;
use App\Modules\Ai\Services\GeminiQuota;
use App\Modules\Competency\Jobs\ExtractCompetenciesJob;
use App\Modules\Industry\Jobs\ExtractJobSkillsJob;
use App\Modules\Matching\Jobs\RunAnalysisJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AiQuotaApiTest extends TestCase
{
    use RefreshDatabase;

    private GeminiQuota $quota;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'skillbridge.gemini.mode' => 'gemini',
            'skillbridge.gemini.limits.enabled' => true,
            'skillbridge.gemini.limits.redis_prefix' => 'test-quota-api:'.bin2hex(random_bytes(6)),
            'skillbridge.gemini.limits.flash.rpm' => 5,
            'skillbridge.gemini.limits.flash.rpd' => 3,
        ]);

        $this->quota = new GeminiQuota;
        $this->user = User::factory()->create(['role' => 'student']);
    }

    protected function tearDown(): void
    {
        $this->quota->reset(GeminiQuota::BUCKET_FLASH);

        parent::tearDown();
    }

    public function test_endpoints_that_start_ai_work_are_rate_limited_per_user(): void
    {
        config(['skillbridge.rate_limits.ai_per_minute' => 1]);

        $this->actingAs($this->user)->postJson('/api/jobs', [])->assertStatus(422);

        $this->actingAs($this->user)->postJson('/api/jobs', [])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Terlalu banyak permintaan proses AI. Coba lagi dalam satu menit.');
    }

    public function test_quota_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/ai/quota')->assertUnauthorized();
    }

    public function test_quota_endpoint_shows_the_remaining_daily_budget_and_reset_time_only(): void
    {
        $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 2);

        $this->actingAs($this->user)->getJson('/api/ai/quota')
            ->assertOk()
            ->assertExactJson(['data' => [
                'available' => true,
                'daily_remaining' => 1,
                'resets_at' => $this->quota->resetsAt()->toIso8601String(),
            ]]);
    }

    public function test_quota_endpoint_reports_unavailable_when_the_day_is_used_up(): void
    {
        $this->useUpTheDay();

        $this->actingAs($this->user)->getJson('/api/ai/quota')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.daily_remaining', 0);
    }

    public function test_quota_endpoint_reports_the_configured_budget_when_counters_are_disabled(): void
    {
        config(['skillbridge.gemini.limits.enabled' => false]);

        $this->actingAs($this->user)->getJson('/api/ai/quota')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.daily_remaining', 3);
    }

    public function test_creating_a_job_posting_is_refused_with_429_when_the_day_is_used_up(): void
    {
        Queue::fake();
        $this->useUpTheDay();
        $career = Career::factory()->create();

        $response = $this->actingAs($this->user)->postJson('/api/jobs', [
            'career_id' => $career->id,
            'title' => 'Backend Developer',
            'company' => 'PT Contoh',
            'description' => 'Membangun API dengan Laravel dan PostgreSQL.',
        ]);

        $this->assertQuotaRefusal($response);
        $this->assertDatabaseCount('job_postings', 0);
        Queue::assertNotPushed(ExtractJobSkillsJob::class);
    }

    public function test_starting_competency_extraction_is_refused_and_leaves_the_document_untouched(): void
    {
        Queue::fake();
        $this->useUpTheDay();
        $document = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'parse_status' => ParseStatus::Parsed,
            'extraction_status' => ExtractionStatus::Pending,
            'detected_metadata' => ['code' => 'IF301', 'name' => 'Pemrograman Web'],
        ]);

        $this->assertQuotaRefusal($this->actingAs($this->user)->postJson("/api/rps/{$document->id}/extract"));

        $this->assertSame(ExtractionStatus::Pending, $document->refresh()->extraction_status);
        $this->assertNull($document->metadata_confirmed_at);
        Queue::assertNotPushed(ExtractCompetenciesJob::class);
    }

    public function test_starting_an_analysis_is_refused_when_the_day_is_used_up(): void
    {
        Queue::fake();
        $this->useUpTheDay();
        [$rps, $career, $posting] = $this->analysisPrerequisites();

        $this->assertQuotaRefusal($this->actingAs($this->user)->postJson('/api/analyses', [
            'rps_document_id' => $rps->id,
            'career_id' => $career->id,
        ]));

        $this->assertSame(0, Analysis::count());
        Queue::assertNotPushed(RunAnalysisJob::class);
    }

    public function test_requests_still_pass_while_budget_remains(): void
    {
        Queue::fake();
        $this->quota->reserve(GeminiQuota::BUCKET_FLASH, 2);
        $career = Career::factory()->create();

        $this->actingAs($this->user)->postJson('/api/jobs', [
            'career_id' => $career->id,
            'title' => 'Backend Developer',
            'company' => 'PT Contoh',
            'description' => 'Membangun API dengan Laravel dan PostgreSQL.',
        ])->assertStatus(202);

        Queue::assertPushed(ExtractJobSkillsJob::class);
    }

    public function test_local_fallback_mode_is_never_blocked_by_the_daily_budget(): void
    {
        Queue::fake();
        config(['skillbridge.gemini.mode' => 'fallback']);
        $this->useUpTheDay();
        $career = Career::factory()->create();

        $this->actingAs($this->user)->postJson('/api/jobs', [
            'career_id' => $career->id,
            'title' => 'Backend Developer',
            'company' => 'PT Contoh',
            'description' => 'Membangun API dengan Laravel dan PostgreSQL.',
        ])->assertStatus(202);
    }

    private function useUpTheDay(): void
    {
        foreach ([1, 1, 1] as $count) {
            $this->quota->reserve(GeminiQuota::BUCKET_FLASH, $count);
            usleep(1000);
        }
    }

    private function assertQuotaRefusal(TestResponse $response): void
    {
        $response->assertStatus(429)
            ->assertJsonPath('code', 'quota_exhausted')
            ->assertJsonPath('resets_at', $this->quota->resetsAt()->toIso8601String());

        $this->assertStringContainsString('Kuota AI harian habis', (string) $response->json('message'));
    }

    /**
     * @return array{0: RpsDocument, 1: Career, 2: JobPosting}
     */
    private function analysisPrerequisites(): array
    {
        $career = Career::factory()->create();
        $rps = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => Course::factory()->create()->id,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Confirmed,
        ]);
        $posting = JobPosting::factory()->create([
            'career_id' => $career->id,
            'extraction_status' => ExtractionStatus::Completed,
            'created_by' => null,
        ]);

        return [$rps, $career, $posting];
    }
}
