<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Rps\Enums\ExtractionStatus;
use Database\Factories\CareerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description'])]
class Career extends Model
{
    /** @use HasFactory<CareerFactory> */
    use HasFactory;

    /**
     * Adds `eligible_job_count`: completed postings the user may read, i.e. the pool an analysis draws from.
     *
     * @param  Builder<Career>  $query
     */
    public function scopeWithEligibleJobCount(Builder $query, User $user): void
    {
        $query->withCount(['jobPostings as eligible_job_count' => fn (Builder $q) => $q
            ->visibleTo($user)
            ->where('extraction_status', ExtractionStatus::Completed),
        ]);
    }

    /**
     * @return HasMany<JobPosting, $this>
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }
}
