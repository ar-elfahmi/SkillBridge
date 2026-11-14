<?php

declare(strict_types=1);

namespace App\Modules\Recommendation\Http\Resources;

use App\Models\UserSkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserSkill
 */
class UserSkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill_id' => $this->skill_id,
            'skill' => $this->skill?->canonical_name ?? '',
            'canonical_skill' => $this->skill?->canonical_name ?? '',
            'reported_as' => $this->reported_as,
            'level' => $this->level->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}