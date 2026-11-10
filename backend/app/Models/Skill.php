<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SkillCategory;
use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Competency dictionary. Normalization (Modules/Competency) maps raw names to
 * canonical_name via aliases, case-insensitively.
 *
 * @property string $canonical_name
 * @property SkillCategory|null $category
 * @property list<string> $aliases
 */
#[Fillable(['canonical_name', 'category', 'aliases'])]
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'aliases' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SkillCategory::class,
            'aliases' => 'array',
        ];
    }

    /**
     * @return HasMany<CourseSkill, $this>
     */
    public function courseSkills(): HasMany
    {
        return $this->hasMany(CourseSkill::class);
    }

    /**
     * @return HasMany<JobSkill, $this>
     */
    public function jobSkills(): HasMany
    {
        return $this->hasMany(JobSkill::class);
    }

    /**
     * Canonical name plus aliases, for matching / technology-name checks.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique([$this->canonical_name, ...$this->aliases]));
    }
}
