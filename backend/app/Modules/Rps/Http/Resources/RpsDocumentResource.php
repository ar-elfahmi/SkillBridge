<?php

declare(strict_types=1);

namespace App\Modules\Rps\Http\Resources;

use App\Models\RpsDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape follows docs/API.md §3 (GET /api/rps/{id}). Never exposes file_path.
 *
 * @mixin RpsDocument
 */
class RpsDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'parse_status' => $this->parse_status->value,
            'extraction_status' => $this->extraction_status->value,
            'review_status' => $this->review_status->value,
            'course' => $this->course === null ? null : [
                'code' => $this->course->code,
                'name' => $this->course->name,
                'credits' => $this->course->credits,
                'semester' => $this->course->semester,
                'study_program' => $this->course->study_program,
            ],
            'detected_metadata' => $this->detected_metadata === null ? null : [
                'code' => $this->detected_metadata['code'] ?? null,
                'name' => $this->detected_metadata['name'] ?? null,
                'credits' => isset($this->detected_metadata['credits']) && $this->detected_metadata['credits'] !== null
                    ? (int) $this->detected_metadata['credits']
                    : null,
                'semester' => isset($this->detected_metadata['semester']) && $this->detected_metadata['semester'] !== null
                    ? (int) $this->detected_metadata['semester']
                    : null,
                'study_program' => $this->detected_metadata['study_program'] ?? null,
            ],
            'page_count' => $this->page_count,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
