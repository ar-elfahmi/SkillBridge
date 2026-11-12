<?php

declare(strict_types=1);

namespace Tests\Feature\Matching;

use App\Enums\AnalysisStatus;
use App\Enums\JudgedBy;
use App\Enums\MatchClassification;
use App\Enums\SkillCategory;
use App\Models\Analysis;
use App\Models\Career;
use App\Models\Course;
use App\Models\CourseSkill;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\RpsDocument;
use App\Models\Skill;
use App\Models\SkillMatch;
use App\Models\User;
use App\Modules\Matching\Jobs\RunAnalysisJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Career $career;

    private RpsDocument $rpsDocument;

    private JobPosting $jobPosting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->career = Career::factory()->create(['name' => 'Backend Developer']);

        $course = Course::factory()->create(['name' => 'Pemrograman Web']);

        $this->rpsDocument = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'course_id' => $course->id,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Confirmed,
        ]);

        $this->jobPosting = JobPosting::factory()->create([
            'career_id' => $this->career->id,
            'extraction_status' => ExtractionStatus::Completed,
            'created_by' => null,
        ]);
    }

    public function test_creates_analysis_returns_202_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)->postJson('/api/analyses', [
            'rps_document_id' => $this->rpsDocument->id,
            'career_id' => $this->career->id,
            'job_posting_ids' => [$this->jobPosting->id],
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'data' => [
                    'analysis_id',
                    'status',
                ],
            ])
            ->assertJson([
                'data' => [
                    'status' => 'queued',
                ],
            ]);

        $analysisId = $response->json('data.analysis_id');
        $this->assertDatabaseHas('analyses', [
            'id' => $analysisId,
            'user_id' => $this->user->id,
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('analysis_job_postings', [
            'analysis_id' => $analysisId,
            'job_posting_id' => $this->jobPosting->id,
        ]);

        Queue::assertPushed(RunAnalysisJob::class, function (RunAnalysisJob $job) use ($analysisId): bool {
            return $job->analysisId === $analysisId;
        });
    }

    public function test_rejects_with_422_when_rps_is_unconfirmed(): void
    {
        $unconfirmedRps = RpsDocument::factory()->create([
            'user_id' => $this->user->id,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Unreviewed,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/analyses', [
            'rps_document_id' => $unconfirmedRps->id,
            'career_id' => $this->career->id,
            'job_posting_ids' => [$this->jobPosting->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Dokumen RPS belum selesai diekstrak atau belum dikonfirmasi.',
            ]);
    }

    public function test_rejects_with_422_when_job_posting_career_mismatches(): void
    {
        $otherCareer = Career::factory()->create(['name' => 'Data Analyst']);
        $mismatchedJob = JobPosting::factory()->create([
            'career_id' => $otherCareer->id,
            'extraction_status' => ExtractionStatus::Completed,
            'created_by' => null,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/analyses', [
            'rps_document_id' => $this->rpsDocument->id,
            'career_id' => $this->career->id,
            'job_posting_ids' => [$mismatchedJob->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Semua lowongan kerja harus sesuai dengan profesi yang dipilih.',
            ]);
    }

    public function test_rejects_with_404_when_rps_belongs_to_another_user(): void
    {
        $otherUser = User::factory()->create();
        $otherRps = RpsDocument::factory()->create([
            'user_id' => $otherUser->id,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Confirmed,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/analyses', [
            'rps_document_id' => $otherRps->id,
            'career_id' => $this->career->id,
            'job_posting_ids' => [$this->jobPosting->id],
        ]);

        $response->assertStatus(404);
    }

    public function test_lists_analyses_scoped_to_authenticated_user(): void
    {
        $myAnalysis = Analysis::factory()->create([
            'user_id' => $this->user->id,
            'rps_document_id' => $this->rpsDocument->id,
            'career_id' => $this->career->id,
        ]);

        $otherUser = User::factory()->create();
        $otherRps = RpsDocument::factory()->create(['user_id' => $otherUser->id]);
        $otherAnalysis = Analysis::factory()->create([
            'user_id' => $otherUser->id,
            'rps_document_id' => $otherRps->id,
            'career_id' => $this->career->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/analyses');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.analysis_id', $myAnalysis->id);
    }

    public function test_shows_analysis_detail_200_for_owner_and_404_for_non_owner(): void
    {
        $analysis = Analysis::factory()->create([
            'user_id' => $this->user->id,
            'rps_document_id' => $this->rpsDocument->id,
            'career_id' => $this->career->id,
            'status' => AnalysisStatus::Completed,
            'summary' => [
                'total_skills' => 10,
                'covered' => 6,
                'partial' => 2,
                'missing' => 2,
                'coverage_percentage' => 60.0,
            ],
            'model_versions' => [
                'prompt_version' => 'match-judge-v1',
            ],
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/analyses/{$analysis->id}");

        $response->assertOk()
            ->assertJsonPath('data.analysis_id', $analysis->id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.career', 'Backend Developer')
            ->assertJsonPath('data.course', 'Pemrograman Web')
            ->assertJsonPath('data.summary.total_skills', 10);

        $this->assertEquals(60.0, $response->json('data.summary.coverage_percentage'));

        // Non-owner gets 404
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->getJson("/api/analyses/{$analysis->id}")->assertStatus(404);
    }

    public function test_shows_matches_breakdown_with_evidence(): void
    {
        $analysis = Analysis::factory()->create([
            'user_id' => $this->user->id,
            'rps_document_id' => $this->rpsDocument->id,
            'career_id' => $this->career->id,
            'status' => AnalysisStatus::Completed,
        ]);

        $skill = Skill::factory()->create([
            'canonical_name' => 'Laravel Framework',
            'category' => SkillCategory::Framework,
        ]);

        $courseSkill = CourseSkill::factory()->create([
            'rps_document_id' => $this->rpsDocument->id,
            'skill_id' => $skill->id,
            'competency' => 'Mampu membuat REST API menggunakan Laravel',
            'evidence' => 'Mahasiswa mampu mengembangkan REST API dengan Laravel',
            'source_reference' => 'CPMK 2',
            'page' => 4,
        ]);

        $jobSkill = JobSkill::factory()->create([
            'job_posting_id' => $this->jobPosting->id,
            'skill_id' => $skill->id,
            'evidence' => 'Pengalaman kerja dengan Laravel',
        ]);

        SkillMatch::create([
            'analysis_id' => $analysis->id,
            'skill_id' => $skill->id,
            'demand_count' => 1,
            'source_job_skill_ids' => [$jobSkill->id],
            'course_skill_id' => $courseSkill->id,
            'similarity_score' => 0.92,
            'classification' => MatchClassification::Covered,
            'reason' => 'Sub-CPMK menyebut framework Laravel.',
            'evidence' => 'Mahasiswa mampu mengembangkan REST API dengan Laravel',
            'candidates' => [],
            'judged_by' => JudgedBy::Llm,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/analyses/{$analysis->id}/matches");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.skill', 'Laravel Framework')
            ->assertJsonPath('data.0.classification', 'covered')
            ->assertJsonPath('data.0.similarity_score', 0.92)
            ->assertJsonPath('data.0.matched_competency.id', $courseSkill->id)
            ->assertJsonPath('data.0.matched_competency.source_reference', 'CPMK 2')
            ->assertJsonPath('data.0.job_evidence.0.job_posting_id', $this->jobPosting->id)
            ->assertJsonPath('data.0.job_evidence.0.evidence', 'Pengalaman kerja dengan Laravel');
    }

    public function test_deletes_analysis_returns_204_and_cascades(): void
    {
        $analysis = Analysis::factory()->create([
            'user_id' => $this->user->id,
            'rps_document_id' => $this->rpsDocument->id,
            'career_id' => $this->career->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/analyses/{$analysis->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('analyses', ['id' => $analysis->id]);
    }
}
