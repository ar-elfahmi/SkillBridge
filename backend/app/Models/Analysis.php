<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnalysisStatus;
use Database\Factories\AnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One RPS × one career × one or more job postings of that career.
 *
 * @property int $user_id
 * @property AnalysisStatus $status
 * @property array{total_skills: int, covered: int, partial: int, missing: int, coverage_percentage: float}|null $summary
 * @property array<string, mixed>|null $model_versions
 */
#[Fillable(['user_id', 'rps_document_id', 'career_id', 'status', 'error_message', 'summary', 'model_versions'])]
class Analysis extends Model
{
    /** @use HasFactory<AnalysisFactory> */
    use HasFactory;

    protected $table = 'analyses';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'summary' => 'array',
            'model_versions' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<RpsDocument, $this>
     */
    public function rpsDocument(): BelongsTo
    {
        return $this->belongsTo(RpsDocument::class);
    }

    /**
     * @return BelongsTo<Career, $this>
     */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    /**
     * @return BelongsToMany<JobPosting, $this>
     */
    public function jobPostings(): BelongsToMany
    {
        return $this->belongsToMany(JobPosting::class, 'analysis_job_postings');
    }

    /**
     * @return HasMany<SkillMatch, $this>
     */
    public function skillMatches(): HasMany
    {
        return $this->hasMany(SkillMatch::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
