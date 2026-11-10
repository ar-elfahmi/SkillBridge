<?php

declare(strict_types=1);

namespace Tests\Feature\Recommendation;

use App\Enums\MatchClassification;
use App\Enums\RecommendationPriority;
use App\Models\SkillMatch;
use App\Modules\Recommendation\Services\PriorityCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PriorityCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): PriorityCalculator
    {
        return app(PriorityCalculator::class);
    }

    /**
     * Rules table from docs/AI_PIPELINE.md §7 with the default 50% threshold.
     *
     * @return array<string, array{MatchClassification, int, int, RecommendationPriority|null}>
     */
    public static function defaultRules(): array
    {
        $m = MatchClassification::Missing;
        $p = MatchClassification::Partial;
        $c = MatchClassification::Covered;

        return [
            'missing, asked by all postings' => [$m, 2, 2, RecommendationPriority::High],
            'missing, exactly 50%' => [$m, 1, 2, RecommendationPriority::High],
            'missing, below 50%' => [$m, 1, 3, RecommendationPriority::Medium],
            'partial, exactly 50%' => [$p, 2, 4, RecommendationPriority::Medium],
            'partial, above 50%' => [$p, 3, 4, RecommendationPriority::Medium],
            'partial, below 50%' => [$p, 1, 4, RecommendationPriority::Low],
            'covered, high demand' => [$c, 4, 4, null],
            'covered, low demand' => [$c, 1, 4, null],
            'missing, no postings selected' => [$m, 0, 0, RecommendationPriority::Medium],
            'partial, no postings selected' => [$p, 0, 0, RecommendationPriority::Low],
        ];
    }

    #[DataProvider('defaultRules')]
    public function test_default_rules(MatchClassification $classification, int $demand, int $postings, ?RecommendationPriority $expected): void
    {
        $this->assertSame($expected, $this->calc()->calculate($classification, $demand, $postings));
    }

    public function test_threshold_comes_from_config(): void
    {
        config(['skillbridge.recommendation.priority.high_demand_ratio' => 0.75]);

        $this->assertSame(RecommendationPriority::Medium, $this->calc()->calculate(MatchClassification::Missing, 2, 4));
        $this->assertSame(RecommendationPriority::High, $this->calc()->calculate(MatchClassification::Missing, 3, 4));
    }

    public function test_priority_per_rule_comes_from_config(): void
    {
        config(['skillbridge.recommendation.priority.partial' => 'medium']);

        $this->assertSame(RecommendationPriority::Medium, $this->calc()->calculate(MatchClassification::Partial, 1, 4));
    }

    public function test_invalid_config_value_fails_loudly(): void
    {
        config(['skillbridge.recommendation.priority.missing' => 'urgent']);

        $this->expectException(InvalidArgumentException::class);

        $this->calc()->calculate(MatchClassification::Missing, 1, 4);
    }

    public function test_negative_counts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calc()->calculate(MatchClassification::Missing, -1, 2);
    }

    public function test_for_match_uses_classification_and_demand_count(): void
    {
        $match = SkillMatch::factory()->make(['classification' => MatchClassification::Missing, 'demand_count' => 2]);

        $this->assertSame(RecommendationPriority::High, $this->calc()->forMatch($match, 2));
        $this->assertSame(RecommendationPriority::Medium, $this->calc()->forMatch($match, 5));
    }
}
