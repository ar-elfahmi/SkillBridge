<?php

declare(strict_types=1);

namespace Tests\Feature\Recommendation;

use App\Enums\RecommendationPriority;
use App\Models\Recommendation;
use App\Models\SkillMatch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_and_relations(): void
    {
        $recommendation = Recommendation::factory()->create()->fresh();

        $this->assertNotNull($recommendation);
        $this->assertSame(RecommendationPriority::High, $recommendation->priority);
        $this->assertSame(['Image & container', 'Dockerfile'], $recommendation->details['topics'] ?? null);
        $this->assertSame($recommendation->skillMatch->analysis_id, $recommendation->analysis_id);
        $this->assertTrue($recommendation->analysis->recommendations->contains($recommendation));
        $this->assertTrue($recommendation->skillMatch->recommendation?->is($recommendation));
    }

    public function test_one_recommendation_per_skill_match(): void
    {
        $recommendation = Recommendation::factory()->create();

        $this->expectException(QueryException::class);

        Recommendation::factory()->create(['skill_match_id' => $recommendation->skill_match_id]);
    }

    public function test_details_default_to_empty_object(): void
    {
        $match = SkillMatch::factory()->create();
        $recommendation = Recommendation::create([
            'analysis_id' => $match->analysis_id,
            'skill_match_id' => $match->id,
            'title' => 'x',
            'reason' => 'y',
            'priority' => RecommendationPriority::Low,
        ])->fresh();

        $this->assertSame([], $recommendation?->details);
    }

    public function test_cascades_with_skill_match_and_analysis(): void
    {
        $first = Recommendation::factory()->create();
        $first->skillMatch->delete();
        $this->assertModelMissing($first);

        $second = Recommendation::factory()->create();
        $second->analysis->delete();
        $this->assertModelMissing($second);
    }
}
