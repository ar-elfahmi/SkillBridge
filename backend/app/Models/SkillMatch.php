<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JudgedBy;
use App\Enums\MatchClassification;
use Database\Factories\SkillMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Classification of one industry skill against the RPS in an analysis.
 * similarity_score is a candidate score, never a mastery percentage.
 *
 * @property MatchClassification $classification
 * @property JudgedBy $judged_by
 * @property list<int> $source_job_skill_ids
 * @property list<array{course_skill_id: int, score: float}> $candidates
 */
#[Fillable([
    'analysis_id', 'skill_id', 'demand_count', 'source_job_skill_ids', 'course_skill_id',
    'similarity_score', 'classification', 'reason', 'evidence', 'candidates', 'judged_by',
])]
class SkillMatch extends Model
{
    /** @use HasFactory<SkillMatchFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source_job_skill_ids' => '[]',
        'candidates' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'demand_count' => 'integer',
            'source_job_skill_ids' => 'array',
            // decimal(6,5); cast keeps 5 digits as a string to avoid float noise.
            'similarity_score' => 'decimal:5',
            'classification' => MatchClassification::class,
            'candidates' => 'array',
            'judged_by' => JudgedBy::class,
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
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /**
     * @return BelongsTo<CourseSkill, $this>
     */
    public function courseSkill(): BelongsTo
    {
        return $this->belongsTo(CourseSkill::class);
    }
}
