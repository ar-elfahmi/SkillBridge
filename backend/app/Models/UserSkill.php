<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SkillLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A skill the user reports learning outside the RPS. Never verified and never
 * part of coverage: it only hides the skill from recommendations.
 *
 * @property int $user_id
 * @property int $skill_id
 * @property string|null $reported_as
 * @property SkillLevel $level
 */
#[Fillable(['user_id', 'skill_id', 'reported_as', 'level'])]
class UserSkill extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => SkillLevel::class,
        ];
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}