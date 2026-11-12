<?php

declare(strict_types=1);

namespace Tests\Feature\Matching;

use App\Enums\AnalysisStatus;
use App\Enums\CompetencyReviewStatus;
use App\Enums\MatchClassification;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\Analysis;
use App\Models\Career;
use App\Models\CourseSkill;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\RpsDocument;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Matching\Jobs\RunAnalysisJob;
use App\Modules\Matching\Services\CandidateFinder;
use App\Modules\Matching\Services\MatchJudge;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    private const string GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('skillbridge.gemini.api_key', 'test-key');
        Config::set('skillbridge.gemini.flash_model', 'gemini-2.5-flash');
        Config::set('skillbridge.gemini.embedding_model', 'gemini-embedding-001');
        Config::set('skillbridge.gemini.embedding_dimensions', 768);
        Config::set('skillbridge.matching.sim_floor', 0.50);
        Config::set('skillbridge.matching.top_k', 3);
    }

    public function test_runs_analysis_aggregates_skills_judges_and_computes_summary(): void
    {
        $user = User::factory()->create();
        $career = Career::factory()->create(['name' => 'Backend Developer']);

        $rps = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Confirmed,
        ]);

        $skillPhp = Skill::factory()->create([
            'canonical_name' => 'PHP',
            'category' => SkillCategory::Language,
        ]);

        $skillDocker = Skill::factory()->create([
            'canonical_name' => 'Docker',
            'category' => SkillCategory::Tool,
        ]);

        // RPS course skills with embeddings
        $csPhp = CourseSkill::factory()->create([
            'rps_document_id' => $rps->id,
            'skill_id' => $skillPhp->id,
            'competency' => 'Mampu membuat aplikasi web dengan PHP modern',
            'evidence' => 'Praktikum PHP lanjut',
            'verification_status' => VerificationStatus::Verified,
            'review_status' => CompetencyReviewStatus::Pending,
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        // Two job postings requesting PHP and Docker
        $job1 = JobPosting::factory()->create([
            'career_id' => $career->id,
            'extraction_status' => ExtractionStatus::Completed,
            'company' => 'PT Satu',
        ]);
        $js1Php = JobSkill::factory()->create([
            'job_posting_id' => $job1->id,
            'skill_id' => $skillPhp->id,
            'skill' => 'PHP',
            'requirement_text' => 'Menguasai PHP 8',
            'evidence' => 'PHP 8',
            'embedding' => [0.95, 0.05, 0.0], // High similarity with csPhp
        ]);
        $js1Docker = JobSkill::factory()->create([
            'job_posting_id' => $job1->id,
            'skill_id' => $skillDocker->id,
            'skill' => 'Docker',
            'requirement_text' => 'Bisa Docker container',
            'evidence' => 'Docker container',
            'embedding' => [0.0, 1.0, 0.0], // Orthogonal to csPhp -> score 0.0 < sim_floor 0.50
        ]);

        $job2 = JobPosting::factory()->create([
            'career_id' => $career->id,
            'extraction_status' => ExtractionStatus::Completed,
            'company' => 'PT Dua',
        ]);
        $js2Php = JobSkill::factory()->create([
            'job_posting_id' => $job2->id,
            'skill_id' => $skillPhp->id,
            'skill' => 'PHP',
            'requirement_text' => 'Pengalaman mendalam mengembangkan API dengan PHP 8 dan OOP', // Longer text -> representative
            'evidence' => 'PHP 8 dan OOP',
            'embedding' => [0.98, 0.02, 0.0],
        ]);

        $analysis = Analysis::factory()->create([
            'user_id' => $user->id,
            'rps_document_id' => $rps->id,
            'career_id' => $career->id,
            'status' => AnalysisStatus::Queued,
        ]);
        $analysis->jobPostings()->sync([$job1->id, $job2->id]);

        // Mock LLM response for PHP (Docker will be skipped from LLM call due to score 0.0 < sim_floor)
        Http::fake([
            self::GEMINI_URL => Http::response([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'classification' => 'covered',
                                        'matched_course_skill_id' => $csPhp->id,
                                        'reason' => 'Mahasiswa mempelajari PHP modern secara lengkap.',
                                        'evidence' => 'Praktikum PHP lanjut',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        app(RunAnalysisJob::class, ['analysisId' => $analysis->id])->handle(
            app(CandidateFinder::class),
            app(MatchJudge::class)
        );

        $analysis->refresh();

        $this->assertSame(AnalysisStatus::Completed, $analysis->status);
        $this->assertNull($analysis->error_message);

        // Verify summary: 2 unique industry skills (PHP and Docker)
        // PHP = covered, Docker = missing
        // coverage_percentage = (1 / 2) * 100 = 50.0
        $this->assertNotNull($analysis->summary);
        $this->assertSame(2, $analysis->summary['total_skills']);
        $this->assertSame(1, $analysis->summary['covered']);
        $this->assertSame(0, $analysis->summary['partial']);
        $this->assertSame(1, $analysis->summary['missing']);
        $this->assertEquals(50.0, $analysis->summary['coverage_percentage']);

        // Verify skill matches in database
        $matches = $analysis->skillMatches()->get();
        $this->assertCount(2, $matches);

        $phpMatch = $matches->firstWhere('skill_id', $skillPhp->id);
        $this->assertNotNull($phpMatch);
        $this->assertSame(2, $phpMatch->demand_count); // Requested by both job1 and job2
        $this->assertSame(MatchClassification::Covered, $phpMatch->classification);
        $this->assertSame($csPhp->id, $phpMatch->course_skill_id);
        $this->assertSame('Praktikum PHP lanjut', $phpMatch->evidence);
        $this->assertCount(2, $phpMatch->source_job_skill_ids);

        $dockerMatch = $matches->firstWhere('skill_id', $skillDocker->id);
        $this->assertNotNull($dockerMatch);
        $this->assertSame(1, $dockerMatch->demand_count); // Requested only by job1
        $this->assertSame(MatchClassification::Missing, $dockerMatch->classification);
        $this->assertNull($dockerMatch->course_skill_id);
        $this->assertNull($dockerMatch->evidence);
    }

    public function test_fails_when_prerequisites_are_not_met(): void
    {
        $user = User::factory()->create();
        $career = Career::factory()->create();

        // RPS not confirmed
        $rps = RpsDocument::factory()->create([
            'user_id' => $user->id,
            'extraction_status' => ExtractionStatus::Completed,
            'review_status' => ReviewStatus::Unreviewed,
        ]);

        $job = JobPosting::factory()->create([
            'career_id' => $career->id,
            'extraction_status' => ExtractionStatus::Completed,
        ]);

        $analysis = Analysis::factory()->create([
            'user_id' => $user->id,
            'rps_document_id' => $rps->id,
            'career_id' => $career->id,
            'status' => AnalysisStatus::Queued,
        ]);
        $analysis->jobPostings()->sync([$job->id]);

        app(RunAnalysisJob::class, ['analysisId' => $analysis->id])->handle(
            app(CandidateFinder::class),
            app(MatchJudge::class)
        );

        $analysis->refresh();
        $this->assertSame(AnalysisStatus::Failed, $analysis->status);
        $this->assertNotNull($analysis->error_message);
    }
}
