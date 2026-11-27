<?php

declare(strict_types=1);

namespace App\Modules\Industry\Http\Controllers;

use App\Models\Career;
use App\Modules\Industry\Http\Resources\CareerResource;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class CareerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // Postings the user can read and whose skills are extracted: the pool an analysis draws from.
        $careers = Career::withCount(['jobPostings as eligible_job_count' => fn (Builder $q) => $q
            ->visibleTo($user)
            ->where('extraction_status', ExtractionStatus::Completed),
        ])->orderBy('name')->get();

        return CareerResource::collection($careers);
    }
}
