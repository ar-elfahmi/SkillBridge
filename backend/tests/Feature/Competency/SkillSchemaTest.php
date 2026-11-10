<?php

declare(strict_types=1);

namespace Tests\Feature\Competency;

use App\Enums\CompetencyOrigin;
use App\Enums\CompetencyReviewStatus;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\CourseSkill;
use App\Models\RpsDocument;
use App\Models\Skill;
use Database\Seeders\SkillSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_dictionary_with_expected_aliases(): void
    {
        $this->seed(SkillSeeder::class);

        $laravel = Skill::where('canonical_name', 'Laravel Framework')->sole();
        $this->assertSame(SkillCategory::Framework, $laravel->category);
        $this->assertContains('PHP Laravel', $laravel->aliases);

        // Examples from docs/AI_PIPELINE.md §3.
        $this->assertContains('Git Version Control', Skill::where('canonical_name', 'Git')->sole()->aliases);
        $this->assertContains('RESTful API', Skill::where('canonical_name', 'REST API')->sole()->aliases);
        $this->assertSame(SkillCategory::Tool, Skill::where('canonical_name', 'Docker')->sole()->category);
    }

    public function test_seeder_is_idempotent_and_keeps_manual_aliases(): void
    {
        $this->seed(SkillSeeder::class);
        $git = Skill::where('canonical_name', 'Git')->sole();
        $git->update(['aliases' => [...$git->aliases, 'Git CLI']]);

        $this->seed(SkillSeeder::class);

        $this->assertSame(count(SkillSeeder::dictionary()), Skill::count());
        $this->assertContains('Git CLI', $git->fresh()?->aliases ?? []);
    }

    public function test_dictionary_names_and_aliases_do_not_collide(): void
    {
        $seen = [];

        foreach (SkillSeeder::dictionary() as $name => [, $aliases]) {
            foreach ([$name, ...$aliases] as $term) {
                $key = mb_strtolower(trim($term));
                $this->assertArrayNotHasKey($key, $seen, "'{$term}' maps to both '{$name}' and '".($seen[$key] ?? '')."'");
                $seen[$key] = $name;
            }
        }
    }

    public function test_canonical_name_is_unique_case_insensitively(): void
    {
        Skill::factory()->create(['canonical_name' => 'Git']);

        $this->expectException(QueryException::class);

        Skill::factory()->create(['canonical_name' => 'git']);
    }

    public function test_aliases_default_to_empty_json_array(): void
    {
        $skill = Skill::create(['canonical_name' => 'Kotlin'])->fresh();

        $this->assertSame([], $skill?->aliases);
        $this->assertSame(['Kotlin'], $skill?->names());
    }

    public function test_course_skill_defaults_and_casts(): void
    {
        $courseSkill = CourseSkill::factory()->create([
            'embedding' => [0.1, -0.2, 0.3],
            'embedding_dimensions' => 3,
        ])->fresh();

        $this->assertNotNull($courseSkill);
        $this->assertSame(CompetencyOrigin::Ai, $courseSkill->origin);
        $this->assertSame(CompetencyReviewStatus::Pending, $courseSkill->review_status);
        $this->assertSame(VerificationStatus::Unverified, $courseSkill->verification_status);
        $this->assertFalse($courseSkill->user_edited);
        $this->assertSame([0.1, -0.2, 0.3], $courseSkill->embedding);
        $this->assertArrayNotHasKey('embedding', $courseSkill->toArray());
    }

    public function test_user_origin_competency_may_have_null_evidence_and_page(): void
    {
        $courseSkill = CourseSkill::factory()->create([
            'origin' => CompetencyOrigin::User,
            'evidence' => null,
            'page' => null,
            'source_type' => 'user',
        ]);

        $this->assertNull($courseSkill->fresh()?->evidence);
    }

    public function test_relations_and_cascades(): void
    {
        $skill = Skill::factory()->create();
        $document = RpsDocument::factory()->create();
        $courseSkill = CourseSkill::factory()->create(['rps_document_id' => $document->id, 'skill_id' => $skill->id]);

        $this->assertTrue($document->courseSkills->contains($courseSkill));
        $this->assertTrue($skill->courseSkills->contains($courseSkill));
        $this->assertTrue($courseSkill->skillEntry?->is($skill));

        // Deleting a dictionary entry keeps the competency, un-normalized.
        $skill->delete();
        $this->assertNull($courseSkill->fresh()?->skill_id);

        $document->delete();
        $this->assertModelMissing($courseSkill);
    }

    public function test_usable_for_matching_excludes_rejected(): void
    {
        $kept = CourseSkill::factory()->create(['review_status' => CompetencyReviewStatus::Confirmed]);
        CourseSkill::factory()->create(['review_status' => CompetencyReviewStatus::Rejected]);

        $this->assertSame([$kept->id], CourseSkill::usableForMatching()->pluck('id')->all());
    }

    public function test_database_seeder_can_run_twice(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 1);
    }
}
