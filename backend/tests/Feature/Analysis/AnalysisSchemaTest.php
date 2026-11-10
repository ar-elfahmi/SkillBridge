<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisStatus;
use App\Enums\JudgedBy;
use App\Enums\MatchClassification;
use App\Models\Analysis;
use App\Models\CourseSkill;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Models\SkillMatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AnalysisSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_analysis_is_queued_and_owned_by_rps_owner(): void
    {
        $analysis = Analysis::factory()->create()->fresh();

        $this->assertNotNull($analysis);
        $this->assertSame(AnalysisStatus::Queued, $analysis->status);
        $this->assertSame($analysis->rpsDocument->user_id, $analysis->user_id);
        $this->assertNull($analysis->summary);
    }

    public function test_summary_and_model_versions_round_trip_as_json(): void
    {
        $summary = ['total_skills' => 12, 'covered' => 5, 'partial' => 1, 'missing' => 6, 'coverage_percentage' => 41.67];
        $versions = ['embedding_model' => 'gemini-embedding-001', 'embedding_dimensions' => 768, 'prompt_version' => 'v1'];

        $analysis = Analysis::factory()->create(['summary' => $summary, 'model_versions' => $versions])->fresh();

        // jsonb does not preserve key order; compare by key.
        $this->assertEquals($summary, $analysis?->summary);
        $this->assertEquals($versions, $analysis?->model_versions);
    }

    public function test_job_postings_pivot_is_unique_per_pair(): void
    {
        $analysis = Analysis::factory()->create();
        $posting = JobPosting::factory()->create(['career_id' => $analysis->career_id]);

        $analysis->jobPostings()->attach($posting);
        $this->assertTrue($analysis->jobPostings->contains($posting));
        $this->assertTrue($posting->analyses->contains($analysis));

        $this->expectException(QueryException::class);
        DB::table('analysis_job_postings')->insert(['analysis_id' => $analysis->id, 'job_posting_id' => $posting->id]);
    }

    public function test_skill_match_defaults_casts_and_relations(): void
    {
        $courseSkill = CourseSkill::factory()->create();
        $match = SkillMatch::factory()->create([
            'course_skill_id' => $courseSkill->id,
            'similarity_score' => 0.876543,
            'classification' => MatchClassification::Partial,
            'judged_by' => JudgedBy::Llm,
            'source_job_skill_ids' => [3, 5],
            'candidates' => [['course_skill_id' => $courseSkill->id, 'score' => 0.87654]],
        ])->fresh();

        $this->assertNotNull($match);
        $this->assertSame('0.87654', $match->similarity_score);
        $this->assertSame(MatchClassification::Partial, $match->classification);
        $this->assertSame(JudgedBy::Llm, $match->judged_by);
        $this->assertSame([3, 5], $match->source_job_skill_ids);
        $this->assertSame($courseSkill->id, $match->candidates[0]['course_skill_id']);
        $this->assertTrue($match->courseSkill?->is($courseSkill));
        $this->assertTrue($match->analysis->skillMatches->contains($match));
    }

    public function test_jsonb_lists_default_to_empty(): void
    {
        $id = DB::table('skill_matches')->insertGetId([
            'analysis_id' => Analysis::factory()->create()->id,
            'skill_id' => Skill::factory()->create()->id,
            'demand_count' => 1,
            'classification' => 'missing',
            'reason' => 'x',
            'judged_by' => 'rule',
        ]);

        $match = SkillMatch::findOrFail($id);
        $this->assertSame([], $match->source_job_skill_ids);
        $this->assertSame([], $match->candidates);
    }

    public function test_skill_is_unique_per_analysis(): void
    {
        $match = SkillMatch::factory()->create();

        $this->expectException(QueryException::class);

        SkillMatch::factory()->create(['analysis_id' => $match->analysis_id, 'skill_id' => $match->skill_id]);
    }

    public function test_deleting_course_skill_keeps_match_without_link(): void
    {
        $courseSkill = CourseSkill::factory()->create();
        $match = SkillMatch::factory()->create(['course_skill_id' => $courseSkill->id]);

        $courseSkill->delete();

        $this->assertNull($match->fresh()?->course_skill_id);
    }

    public function test_deleting_rps_cascades_analysis_matches_and_pivot(): void
    {
        $analysis = Analysis::factory()->create();
        $analysis->jobPostings()->attach(JobPosting::factory()->create(['career_id' => $analysis->career_id]));
        SkillMatch::factory()->create(['analysis_id' => $analysis->id]);

        $analysis->rpsDocument->delete();

        $this->assertModelMissing($analysis);
        $this->assertDatabaseCount('skill_matches', 0);
        $this->assertDatabaseCount('analysis_job_postings', 0);
    }

    public function test_deleting_user_cascades_through_manual_postings_and_analyses(): void
    {
        $analysis = Analysis::factory()->create();
        $owner = $analysis->user;
        $analysis->jobPostings()->attach(JobPosting::factory()->manual($owner)->create(['career_id' => $analysis->career_id]));

        $owner->delete();

        $this->assertModelMissing($analysis);
        $this->assertDatabaseCount('job_postings', 0);
        $this->assertDatabaseCount('analysis_job_postings', 0);
    }

    public function test_deleting_posting_removes_it_from_analyses_only(): void
    {
        $analysis = Analysis::factory()->create();
        $posting = JobPosting::factory()->create(['career_id' => $analysis->career_id]);
        $analysis->jobPostings()->attach($posting);

        $posting->delete();

        $this->assertModelExists($analysis);
        $this->assertDatabaseCount('analysis_job_postings', 0);
    }

    public function test_policy_is_owner_only_with_404(): void
    {
        $analysis = Analysis::factory()->create();
        $other = User::factory()->create();

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($analysis->user)->allows($ability, $analysis));

            $response = Gate::forUser($other)->inspect($ability, $analysis);
            $this->assertTrue($response->denied());
            $this->assertSame(404, $response->status());
        }
    }
}
