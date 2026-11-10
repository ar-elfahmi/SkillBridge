<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Models\Career;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\Skill;
use App\Models\User;
use App\Modules\Rps\Enums\ExtractionStatus;
use Database\Seeders\CareerSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CareerJobSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_career_seeder_creates_mvp_careers_idempotently(): void
    {
        $this->seed(CareerSeeder::class);
        $this->seed(CareerSeeder::class);

        $this->assertSame(
            collect(CareerSeeder::careers())->keys()->sort()->values()->all(),
            Career::orderBy('slug')->pluck('slug')->all(),
        );
        $this->assertGreaterThanOrEqual(20, Career::count());
        $this->assertContains(
            'backend-developer',
            Career::pluck('slug')->all(),
        );
        $this->assertSame('Backend Developer', Career::where('slug', 'backend-developer')->value('name'));
    }

    public function test_career_slug_is_unique(): void
    {
        Career::factory()->create(['slug' => 'backend-developer']);

        $this->expectException(QueryException::class);

        Career::factory()->create(['slug' => 'backend-developer']);
    }

    public function test_job_posting_defaults_and_casts(): void
    {
        $posting = JobPosting::factory()->create(['collected_at' => '2026-11-01'])->fresh();

        $this->assertNotNull($posting);
        $this->assertTrue($posting->isCurated());
        $this->assertSame(ExtractionStatus::Pending, $posting->extraction_status);
        $this->assertSame('2026-11-01', $posting->collected_at?->toDateString());
    }

    public function test_career_with_postings_cannot_be_deleted(): void
    {
        $posting = JobPosting::factory()->create();

        $this->expectException(QueryException::class);

        $posting->career->delete();
    }

    public function test_job_skills_cascade_with_posting_and_manual_postings_with_user(): void
    {
        $owner = User::factory()->create();
        $posting = JobPosting::factory()->manual($owner)->create();
        $jobSkill = JobSkill::factory()->create(['job_posting_id' => $posting->id]);

        $this->assertTrue($posting->jobSkills->contains($jobSkill));
        $this->assertTrue($jobSkill->skillEntry?->jobSkills->contains($jobSkill));
        $this->assertArrayNotHasKey('embedding', $jobSkill->toArray());

        $owner->delete();

        $this->assertModelMissing($posting);
        $this->assertModelMissing($jobSkill);
    }

    public function test_skill_in_use_by_job_cannot_be_deleted(): void
    {
        $jobSkill = JobSkill::factory()->create();

        $this->expectException(QueryException::class);

        Skill::whereKey($jobSkill->skill_id)->delete();
    }

    public function test_visible_to_scope_returns_curated_and_own_postings_only(): void
    {
        $user = User::factory()->create();
        $curated = JobPosting::factory()->create();
        $own = JobPosting::factory()->manual($user)->create();
        JobPosting::factory()->manual()->create();

        $this->assertEqualsCanonicalizing(
            [$curated->id, $own->id],
            JobPosting::visibleTo($user)->pluck('id')->all(),
        );
    }

    public function test_policy_curated_is_readable_but_not_modifiable(): void
    {
        $user = User::factory()->create();
        $curated = JobPosting::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('view', $curated));

        foreach (['update', 'delete'] as $ability) {
            $response = Gate::forUser($user)->inspect($ability, $curated);
            $this->assertTrue($response->denied());
            $this->assertNotSame(404, $response->status());
            $this->assertSame('Lowongan dari dataset terkurasi tidak dapat diubah.', $response->message());
        }
    }

    public function test_policy_manual_posting_is_owner_only_with_404_for_others(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $posting = JobPosting::factory()->manual($owner)->create();

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $posting));

            $response = Gate::forUser($other)->inspect($ability, $posting);
            $this->assertTrue($response->denied());
            $this->assertSame(404, $response->status());
        }
    }
}
