<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Rps\Enums\ExtractionStatus;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $created_by
 * @property ExtractionStatus $extraction_status
 */
#[Fillable([
    'career_id', 'created_by', 'title', 'company', 'description', 'qualifications',
    'source_name', 'source_url', 'collected_at', 'dataset_version', 'extraction_status',
])]
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'extraction_status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collected_at' => 'date',
            'extraction_status' => ExtractionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Career, $this>
     */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<JobSkill, $this>
     */
    public function jobSkills(): HasMany
    {
        return $this->hasMany(JobSkill::class);
    }

    /**
     * @return BelongsToMany<Analysis, $this>
     */
    public function analyses(): BelongsToMany
    {
        return $this->belongsToMany(Analysis::class, 'analysis_job_postings');
    }

    public function isCurated(): bool
    {
        return $this->created_by === null;
    }

    /**
     * Curated postings plus the user's own manual postings.
     *
     * @param  Builder<JobPosting>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('created_by')->orWhere('created_by', $user->id));
    }
}
