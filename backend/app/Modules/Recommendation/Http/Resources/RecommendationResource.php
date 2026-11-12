<?php

declare(strict_types=1);

namespace App\Modules\Recommendation\Http\Resources;

use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recommendation
 */
class RecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'skill' => $this->skillMatch?->skill?->canonical_name ?? '',
            'priority' => $this->priority->value,
            'title' => $this->title,
            'reason' => $this->reason,
            'details' => [
                'topics' => $this->details['topics'] ?? [],
                'learning_keywords' => $this->details['learning_keywords'] ?? [],
            ],
        ];
    }
}
