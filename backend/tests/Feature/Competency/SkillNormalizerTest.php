<?php

declare(strict_types=1);

namespace Tests\Feature\Competency;

use App\Enums\SkillCategory;
use App\Models\Skill;
use App\Modules\Competency\Services\SkillNormalizer;
use Database\Seeders\SkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkillNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private SkillNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SkillSeeder::class);
        $this->normalizer = new SkillNormalizer;
    }

    public function test_matches_canonical_name_exactly(): void
    {
        $skill = $this->normalizer->normalize('Laravel Framework');

        $this->assertNotNull($skill);
        $this->assertSame('Laravel Framework', $skill->canonical_name);
        $this->assertSame(SkillCategory::Framework, $skill->category);
    }

    public function test_matches_canonical_name_case_insensitively(): void
    {
        $skillLower = $this->normalizer->normalize('laravel framework');
        $this->assertSame('Laravel Framework', $skillLower?->canonical_name);

        $skillUpper = $this->normalizer->normalize('GIT');
        $this->assertSame('Git', $skillUpper?->canonical_name);

        $skillMixed = $this->normalizer->normalize('dOcKeR');
        $this->assertSame('Docker', $skillMixed?->canonical_name);
    }

    public function test_matches_via_aliases_case_insensitively(): void
    {
        // Examples from docs/AI_PIPELINE.md §3
        $laravel = $this->normalizer->normalize('PHP Laravel');
        $this->assertSame('Laravel Framework', $laravel?->canonical_name);

        $laravelLower = $this->normalizer->normalize('php laravel');
        $this->assertSame('Laravel Framework', $laravelLower?->canonical_name);

        $git = $this->normalizer->normalize('Git Version Control');
        $this->assertSame('Git', $git?->canonical_name);

        $restApi = $this->normalizer->normalize('RESTful API');
        $this->assertSame('REST API', $restApi?->canonical_name);

        $docker = $this->normalizer->normalize('Containerization');
        $this->assertSame('Docker', $docker?->canonical_name);

        $ts = $this->normalizer->normalize('ts');
        $this->assertSame('TypeScript', $ts?->canonical_name);
    }

    public function test_normalizes_whitespace(): void
    {
        $skill = $this->normalizer->normalize("  PHP   \t  Laravel  \n ");

        $this->assertNotNull($skill);
        $this->assertSame('Laravel Framework', $skill->canonical_name);
    }

    public function test_returns_null_for_empty_or_whitespace_input(): void
    {
        $this->assertNull($this->normalizer->normalize(''));
        $this->assertNull($this->normalizer->normalize("   \t\n  "));
    }

    public function test_creates_new_skill_entry_when_no_match_found(): void
    {
        $initialCount = Skill::count();

        $skill = $this->normalizer->normalize('Rust Programming', SkillCategory::Language);

        $this->assertNotNull($skill);
        $this->assertSame('Rust Programming', $skill->canonical_name);
        $this->assertSame(SkillCategory::Language, $skill->category);
        $this->assertSame([], $skill->aliases);
        $this->assertSame($initialCount + 1, Skill::count());

        // Subsequent lookup finds the new entry case-insensitively
        $this->normalizer->clearCache();
        $found = $this->normalizer->normalize('rust programming');
        $this->assertSame($skill->id, $found?->id);
        $this->assertSame($initialCount + 1, Skill::count());
    }

    public function test_resolves_string_category(): void
    {
        $skill = $this->normalizer->normalize('Kubernetes', 'tool');

        $this->assertNotNull($skill);
        $this->assertSame('Kubernetes', $skill->canonical_name);
        $this->assertSame(SkillCategory::Tool, $skill->category);

        $skillInvalid = $this->normalizer->normalize('UnknownTech', 'invalid_category');
        $this->assertNull($skillInvalid?->category);
    }

    public function test_in_memory_cache_prevents_duplicate_queries(): void
    {
        // First call queries DB and caches
        $this->normalizer->normalize('Docker');

        // Second call with same name (even different casing/spacing) should hit cache
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $cached1 = $this->normalizer->normalize('Docker');
        $cached2 = $this->normalizer->normalize('  docker  ');

        $this->assertSame(0, $queryCount);
        $this->assertSame('Docker', $cached1?->canonical_name);
        $this->assertSame('Docker', $cached2?->canonical_name);
    }

    public function test_helper_methods_return_expected_values(): void
    {
        $this->assertSame('Git', $this->normalizer->canonicalName('Git Version Control'));

        $gitModel = Skill::where('canonical_name', 'Git')->sole();
        $this->assertSame($gitModel->id, $this->normalizer->skillId('Git Version Control'));

        $this->assertNull($this->normalizer->canonicalName('   '));
        $this->assertNull($this->normalizer->skillId('   '));
    }
}
