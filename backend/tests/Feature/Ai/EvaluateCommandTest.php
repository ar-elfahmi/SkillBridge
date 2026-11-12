<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\Career;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Modules\Industry\Jobs\ExtractJobSkillsJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use Database\Seeders\CareerSeeder;
use Database\Seeders\SampleJobPostingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EvaluateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_command_computes_metrics_and_writes_report_for_golden_set(): void
    {
        $reportPath = storage_path('app/evals/eval-golden.json');
        if (is_file($reportPath)) {
            unlink($reportPath);
        }

        $this->artisan('skillbridge:evaluate', ['--set' => 'golden'])
            ->expectsOutputToContain('Evaluasi Kualitas AI SkillBridge (set: golden)')
            ->expectsOutputToContain('Extraction Accuracy (4 RPS)')
            ->expectsOutputToContain('Matching & Gap Classification (24 pairs')
            ->expectsOutputToContain('Recommendation Relevance (10 sampel)')
            ->assertSuccessful();

        $this->assertFileExists($reportPath);

        $report = json_decode((string) file_get_contents($reportPath), true);
        $this->assertIsArray($report);
        $this->assertSame('golden', $report['dataset']);
        $this->assertSame(4, $report['extraction']['courses_count']);
        $this->assertEquals(1.0, $report['extraction']['evidence_verified_rate']);
        $this->assertSame(24, $report['matching']['total_pairs']);
        $this->assertEquals(1.0, $report['matching']['accuracy']);
        $this->assertEquals(1.0, $report['matching']['macro_f1']);
        $this->assertSame(8, $report['matching']['confusion_matrix']['covered']['covered']);
        $this->assertSame(8, $report['matching']['confusion_matrix']['partial']['partial']);
        $this->assertSame(8, $report['matching']['confusion_matrix']['missing']['missing']);
        $this->assertSame(10, $report['recommendations']['samples_count']);
        $this->assertEquals(1.0, $report['recommendations']['priority_alignment_rate']);
        $this->assertEquals(1.0, $report['recommendations']['url_free_compliance_rate']);
    }

    public function test_evaluate_command_fails_for_nonexistent_dataset(): void
    {
        $this->artisan('skillbridge:evaluate', ['--set' => 'nonexistent_set'])
            ->assertFailed();
    }

    public function test_sample_seeder_populates_completed_job_skills_with_normalized_embeddings(): void
    {
        $this->seed(SampleJobPostingSeeder::class);

        $this->assertSame(8, JobPosting::where('extraction_status', ExtractionStatus::Completed)->count());
        $this->assertGreaterThan(30, JobSkill::count());

        $sampleSkill = JobSkill::first();
        $this->assertNotNull($sampleSkill);
        $this->assertNotNull($sampleSkill->skill_id);
        $this->assertNotEmpty($sampleSkill->embedding);
        $this->assertNotNull($sampleSkill->text_hash);
    }

    public function test_extract_pending_jobs_command_dispatches_jobs_with_limit_and_career_filter(): void
    {
        Queue::fake();
        $this->seed(CareerSeeder::class);

        $backend = Career::where('slug', 'backend-developer')->firstOrFail();
        $frontend = Career::where('slug', 'frontend-developer')->firstOrFail();

        $p1 = JobPosting::factory()->create([
            'career_id' => $backend->id,
            'extraction_status' => ExtractionStatus::Pending,
        ]);
        $p2 = JobPosting::factory()->create([
            'career_id' => $backend->id,
            'extraction_status' => ExtractionStatus::Pending,
        ]);
        JobPosting::factory()->create([
            'career_id' => $frontend->id,
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        $this->artisan('skillbridge:extract-pending-jobs', [
            '--career' => 'backend-developer',
            '--limit' => 1,
        ])
            ->expectsOutputToContain('Dijadwalkan ekstraksi skill untuk 1 lowongan.')
            ->assertSuccessful();

        Queue::assertPushed(ExtractJobSkillsJob::class, 1);
        Queue::assertPushed(ExtractJobSkillsJob::class, fn (ExtractJobSkillsJob $job) => $job->jobPostingId === $p1->id);
        Queue::assertNotPushed(ExtractJobSkillsJob::class, fn (ExtractJobSkillsJob $job) => $job->jobPostingId === $p2->id);
    }
}
