<?php

declare(strict_types=1);

namespace App\Modules\Recommendation\Services;

use App\Enums\MatchClassification;
use App\Enums\RecommendationPriority;
use App\Models\SkillMatch;
use InvalidArgumentException;

/**
 * Deterministic recommendation priority (docs/AI_PIPELINE.md §7), read from
 * config('skillbridge.recommendation.priority'):
 *
 *   missing + demand ratio >= high_demand_ratio -> missing_high_demand (high)
 *   missing                                      -> missing             (medium)
 *   partial + demand ratio >= high_demand_ratio -> partial_high_demand (medium)
 *   partial                                      -> partial             (low)
 *   covered                                      -> null (no recommendation)
 *
 * demand ratio = demand_count / number of job postings selected in the analysis.
 */
class PriorityCalculator
{
    public function calculate(MatchClassification $classification, int $demandCount, int $selectedPostings): ?RecommendationPriority
    {
        if ($classification === MatchClassification::Covered) {
            return null;
        }

        if ($demandCount < 0 || $selectedPostings < 0) {
            throw new InvalidArgumentException('demand_count and selected postings must be >= 0.');
        }

        $rules = (array) config('skillbridge.recommendation.priority');
        $threshold = (float) ($rules['high_demand_ratio'] ?? 0.5);
        $highDemand = $selectedPostings > 0 && ($demandCount / $selectedPostings) >= $threshold;

        $key = match ($classification) {
            MatchClassification::Missing => $highDemand ? 'missing_high_demand' : 'missing',
            MatchClassification::Partial => $highDemand ? 'partial_high_demand' : 'partial',
        };

        $value = $rules[$key] ?? null;
        $priority = is_string($value) ? RecommendationPriority::tryFrom($value) : null;

        if ($priority === null) {
            throw new InvalidArgumentException("Invalid priority config for '{$key}'.");
        }

        return $priority;
    }

    public function forMatch(SkillMatch $match, int $selectedPostings): ?RecommendationPriority
    {
        return $this->calculate($match->classification, $match->demand_count, $selectedPostings);
    }
}
