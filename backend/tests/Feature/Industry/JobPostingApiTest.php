<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Models\Career;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Industry\Jobs\ExtractJobSkillsJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobPostingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_cannot_access_careers_or_jobs(): void
    {
        $career = Career::factory()->create();
        $job = JobPosting::factory()->create(['career_id' => $career->id]);

        $this->getJson('/api/careers')->assertUnauthorized();
        $this->getJson('/api/jobs')->assertUnauthorized();
        $this->getJson("/api/jobs/{$job->id}")->assertUnauthorized();
        $this->postJson('/api/jobs', [])->assertUnauthorized();
    }

    public function test_get_careers_returns_all_careers(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        Career::factory()->create(['name' => 'Backend Developer', 'slug' => 'backend-developer']);
        Career::factory()->create(['name' => 'Frontend Developer', 'slug' => 'frontend-developer']);

        $response = $this->actingAs($user)
            ->getJson('/api/careers')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'description'],
            ],
        ]);
    }

    public function test_get_jobs_returns_paginated_visible_jobs(): void
    {
        $userA = User::factory()->create(['role' => 'student']);
        $userB = User::factory()->create(['role' => 'student']);
        $career = Career::factory()->create();

        // 1 curated job (visible to all)
        $curatedJob = JobPosting::factory()->create([
            'career_id' => $career->id,
            'created_by' => null,
            'title' => 'Curated Backend Role',
        ]);

        // 1 manual job by User A (visible to User A)
        $userAJob = JobPosting::factory()->create([
            'career_id' => $career->id,
            'created_by' => $userA->id,
            'title' => 'User A Backend Role',
        ]);

        // 1 manual job by User B (hidden from User A)
        $userBJob = JobPosting::factory()->create([
            'career_id' => $career->id,
            'created_by' => $userB->id,
            'title' => 'User B Backend Role',
        ]);

        $response = $this->actingAs($userA)
            ->getJson('/api/jobs')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $response->assertJsonFragment(['title' => 'Curated Backend Role']);
        $response->assertJsonFragment(['title' => 'User A Backend Role']);
        $response->assertJsonMissing(['title' => 'User B Backend Role']);
    }

    public function test_get_jobs_filters_by_career_id(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $careerA = Career::factory()->create(['name' => 'Backend']);
        $careerB = Career::factory()->create(['name' => 'Data Analyst']);

        JobPosting::factory()->create(['career_id' => $careerA->id, 'title' => 'Backend Role']);
        JobPosting::factory()->create(['career_id' => $careerB->id, 'title' => 'Analyst Role']);

        $response = $this->actingAs($user)
            ->getJson("/api/jobs?career_id={$careerA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Backend Role'])
            ->assertJsonMissing(['title' => 'Analyst Role']);
    }

    public function test_get_single_job_returns_detail_with_skills_and_evidence(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $career = Career::factory()->create(['name' => 'Full Stack Developer']);
        $job = JobPosting::factory()->create([
            'career_id' => $career->id,
            'created_by' => null,
            'title' => 'Senior Full Stack',
            'company' => 'Tech Corp',
            'description' => 'Great role',
            'source_url' => 'https://example.com/job/100',
        ]);

        $skill = Skill::factory()->create(['canonical_name' => 'TypeScript']);
        $jobSkill = JobSkill::create([
            'job_posting_id' => $job->id,
            'skill_id' => $skill->id,
            'skill' => 'TS',
            'requirement_text' => 'Must be proficient in TypeScript',
            'evidence' => 'proficient in TypeScript',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/jobs/{$job->id}")
            ->assertOk();

        $response->assertJsonPath('data.id', $job->id);
        $response->assertJsonPath('data.title', 'Senior Full Stack');
        $response->assertJsonPath('data.career.name', 'Full Stack Developer');
        $response->assertJsonPath('data.is_curated', true);
        $response->assertJsonCount(1, 'data.skills');

        $response->assertJsonFragment([
            'id' => $jobSkill->id,
            'skill' => 'TS',
            'canonical_skill' => 'TypeScript',
            'requirement_text' => 'Must be proficient in TypeScript',
            'evidence' => 'proficient in TypeScript',
        ]);
    }

    public function test_non_owner_cannot_view_stranger_manual_job(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $stranger = User::factory()->create(['role' => 'student']);
        $career = Career::factory()->create();

        $manualJob = JobPosting::factory()->create([
            'career_id' => $career->id,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($stranger)
            ->getJson("/api/jobs/{$manualJob->id}")
            ->assertNotFound();
    }

    public function test_store_manual_job_validates_payload(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)
            ->postJson('/api/jobs', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['career_id', 'title', 'company', 'description']);

        // Non-existent career
        $this->actingAs($user)
            ->postJson('/api/jobs', [
                'career_id' => 99999,
                'title' => 'Role',
                'company' => 'Corp',
                'description' => 'Desc',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['career_id']);

        // Description > 20000 characters
        $this->actingAs($user)
            ->postJson('/api/jobs', [
                'career_id' => 1,
                'title' => 'Role',
                'company' => 'Corp',
                'description' => str_repeat('a', 20001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_store_manual_job_creates_record_dispatches_job_and_returns_202(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'student']);
        $career = Career::factory()->create(['name' => 'DevOps Engineer']);

        $payload = [
            'career_id' => $career->id,
            'title' => 'Site Reliability Engineer',
            'company' => 'Cloud Inc',
            'description' => 'Managing Kubernetes clusters and CI/CD pipelines.',
            'source_url' => 'https://cloud.example.com/careers/sre',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/jobs', $payload)
            ->assertStatus(202)
            ->assertJsonPath('data.title', 'Site Reliability Engineer')
            ->assertJsonPath('data.company', 'Cloud Inc')
            ->assertJsonPath('data.career.name', 'DevOps Engineer')
            ->assertJsonPath('data.is_curated', false)
            ->assertJsonPath('data.extraction_status', 'pending');

        $jobId = $response->json('data.id');
        $this->assertNotNull($jobId);

        $created = JobPosting::find($jobId);
        $this->assertNotNull($created);
        $this->assertSame($user->id, $created->created_by);
        $this->assertSame('manual', $created->source_name);
        $this->assertSame(ExtractionStatus::Pending, $created->extraction_status);

        Queue::assertPushed(ExtractJobSkillsJob::class, function (ExtractJobSkillsJob $job) use ($created): bool {
            return $job->jobPostingId === $created->id;
        });
    }
}
