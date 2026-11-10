<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompetencyOrigin;
use App\Enums\CompetencyReviewStatus;
use App\Enums\CompetencySourceType;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use Database\Factories\CourseSkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Competency extracted from an RPS (or added by the user), with evidence.
 *
 * @property SkillCategory|null $category
 * @property CompetencySourceType $source_type
 * @property CompetencyOrigin $origin
 * @property CompetencyReviewStatus $review_status
 * @property VerificationStatus $verification_status
 * @property list<float>|null $embedding
 */
#[Fillable([
    'rps_document_id', 'skill_id', 'skill', 'category', 'competency', 'evidence', 'source_type',
    'origin', 'review_status', 'user_edited', 'source_reference', 'week', 'page',
    'verification_status', 'embedding', 'embedding_model', 'embedding_dimensions', 'text_hash',
])]
// Large vector; load explicitly when needed, never send to clients.
#[Hidden(['embedding'])]
class CourseSkill extends Model
{
    /** @use HasFactory<CourseSkillFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'origin' => 'ai',
        'review_status' => 'pending',
        'user_edited' => false,
        'verification_status' => 'unverified',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SkillCategory::class,
            'source_type' => CompetencySourceType::class,
            'origin' => CompetencyOrigin::class,
            'review_status' => CompetencyReviewStatus::class,
            'verification_status' => VerificationStatus::class,
            'user_edited' => 'boolean',
            'week' => 'integer',
            'page' => 'integer',
            'embedding' => 'array',
            'embedding_dimensions' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RpsDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(RpsDocument::class, 'rps_document_id');
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skillEntry(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    /**
     * Competencies usable for matching (rejected ones are kept but excluded).
     *
     * @param  Builder<CourseSkill>  $query
     */
    public function scopeUsableForMatching(Builder $query): void
    {
        $query->where('review_status', '!=', CompetencyReviewStatus::Rejected->value);
    }
}
