<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\JobSkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Skill required by a job posting, with a quote from the posting as evidence.
 *
 * @property list<float>|null $embedding
 */
#[Fillable([
    'job_posting_id', 'skill_id', 'skill', 'requirement_text', 'evidence',
    'embedding', 'embedding_model', 'embedding_dimensions', 'text_hash',
])]
// Large vector; never send to clients.
#[Hidden(['embedding'])]
class JobSkill extends Model
{
    /** @use HasFactory<JobSkillFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedding_dimensions' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * Named skillEntry because "skill" is the raw-name column.
     *
     * @return BelongsTo<Skill, $this>
     */
    public function skillEntry(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
