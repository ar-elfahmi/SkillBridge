<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecommendationPriority;
use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Learning recommendation for one partial/missing skill match.
 * Priority comes from PriorityCalculator; title/reason/details from the LLM.
 *
 * @property RecommendationPriority $priority
 * @property array{topics?: list<string>, learning_keywords?: list<string>} $details
 */
#[Fillable(['analysis_id', 'skill_match_id', 'title', 'reason', 'priority', 'details'])]
class Recommendation extends Model
{
    /** @use HasFactory<RecommendationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'details' => '{}',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => RecommendationPriority::class,
            'details' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Analysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    /**
     * @return BelongsTo<SkillMatch, $this>
     */
    public function skillMatch(): BelongsTo
    {
        return $this->belongsTo(SkillMatch::class);
    }
}
