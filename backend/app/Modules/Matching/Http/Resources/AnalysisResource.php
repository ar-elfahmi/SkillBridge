<?php

declare(strict_types=1);

namespace App\Modules\Matching\Http\Resources;

use App\Models\Analysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Analysis
 */
class AnalysisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $courseName = $this->rpsDocument?->course?->name
            ?? $this->rpsDocument?->detected_metadata['name'] ?? null
            ?? $this->rpsDocument?->original_name;

        return [
            'analysis_id' => $this->id,
            'status' => $this->status->value,
            'career' => $this->career?->name ?? '',
            'course' => $courseName,
            'summary' => $this->summary,
            'model_versions' => $this->model_versions,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
