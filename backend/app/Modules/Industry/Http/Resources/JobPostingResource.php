<?php

declare(strict_types=1);

namespace App\Modules\Industry\Http\Resources;

use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobPosting
 */
class JobPostingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'career_id' => $this->career_id,
            'career' => $this->whenLoaded('career', fn (): ?array => $this->career === null ? null : [
                'id' => $this->career->id,
                'name' => $this->career->name,
                'slug' => $this->career->slug,
            ]),
            'created_by' => $this->created_by,
            'is_curated' => $this->isCurated(),
            'title' => $this->title,
            'company' => $this->company,
            'description' => $this->description,
            'qualifications' => $this->qualifications,
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
            'collected_at' => $this->collected_at?->toDateString(),
            'dataset_version' => $this->dataset_version,
            'extraction_status' => $this->extraction_status->value,
            'skills' => JobSkillResource::collection($this->whenLoaded('jobSkills')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
