<?php

declare(strict_types=1);

namespace App\Modules\Ai\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\RpsDocument;
use App\Modules\Ai\Services\CareerSuggestionService;
use App\Modules\Rps\Enums\ReviewStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CareerSuggestionController extends Controller
{
    /**
     * Career suggestions for an RPS. Statuses: processing, completed, failed, unavailable.
     *
     * Documents confirmed before this feature existed have no result yet, so the first read queues it.
     */
    public function show(RpsDocument $document, CareerSuggestionService $service): JsonResponse
    {
        Gate::authorize('view', $document);

        if ($document->career_suggestions === null && $document->review_status === ReviewStatus::Confirmed) {
            $service->request($document);
            $document->refresh();
        }

        $stored = $document->career_suggestions;

        if ($stored === null) {
            return response()->json(['data' => ['status' => 'unavailable', 'items' => []]]);
        }

        $items = is_array($stored['items'] ?? null) ? $stored['items'] : [];
        $careers = Career::whereIn('id', array_column($items, 'career_id'))->get()->keyBy('id');

        $resolved = [];
        foreach ($items as $item) {
            $career = $careers->get($item['career_id'] ?? 0);
            if ($career !== null) {
                $resolved[] = [
                    'career_id' => $career->id,
                    'name' => $career->name,
                    'reason' => (string) ($item['reason'] ?? ''),
                ];
            }
        }

        return response()->json(['data' => [
            'status' => (string) ($stored['status'] ?? 'failed'),
            'items' => $resolved,
        ]]);
    }
}
