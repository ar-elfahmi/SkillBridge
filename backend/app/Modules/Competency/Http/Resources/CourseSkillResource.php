<?php

declare(strict_types=1);

namespace App\Modules\Competency\Http\Resources;

use App\Models\CourseSkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape follows docs/API.md §3 (GET /api/rps/{id}/competencies).
 *
 * @mixin CourseSkill
 */
class CourseSkillResource extends JsonResource
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
            'category' => $this->category?->value,
            'competency' => $this->competency,
            'evidence' => $this->evidence,
            'source_type' => $this->source_type->value,
            'source_reference' => $this->source_reference,
            'week' => $this->week,
            'page' => $this->page,
            'verification_status' => $this->verification_status->value,
            'origin' => $this->origin->value,
            'review_status' => $this->review_status->value,
            'user_edited' => (bool) $this->user_edited,
        ];
    }
}
