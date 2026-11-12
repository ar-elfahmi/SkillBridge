<?php

declare(strict_types=1);

namespace App\Modules\Industry\Http\Resources;

use App\Models\JobSkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobSkill
 */
class JobSkillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill' => $this->skill,
            'canonical_skill' => $this->skillEntry?->canonical_name ?? $this->skill,
            'category' => $this->skillEntry?->category?->value,
            'requirement_text' => $this->requirement_text,
            'evidence' => $this->evidence,
        ];
    }
}
