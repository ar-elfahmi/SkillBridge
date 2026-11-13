<?php

declare(strict_types=1);

namespace App\Modules\Industry\Http\Controllers;

use App\Models\JobPosting;
use App\Modules\Industry\Http\Requests\StoreJobPostingRequest;
use App\Modules\Industry\Http\Resources\JobPostingResource;
use App\Modules\Industry\Jobs\ExtractJobSkillsJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class JobPostingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = JobPosting::visibleTo($request->user())
            ->with(['career', 'jobSkills.skillEntry'])
            ->orderByDesc('id');

        if ($request->filled('career_id')) {
            $query->where('career_id', $request->integer('career_id'));
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));
        $postings = $query->paginate($perPage);

        return JobPostingResource::collection($postings);
    }

    public function show(Request $request, JobPosting $job): JobPostingResource
    {
        Gate::authorize('view', $job);

        return new JobPostingResource($job->load(['career', 'jobSkills.skillEntry']));
    }

    public function store(StoreJobPostingRequest $request): JsonResponse
    {
        $posting = JobPosting::create([
            'career_id' => $request->integer('career_id'),
            'created_by' => $request->user()->id,
            'title' => $request->string('title')->trim()->toString(),
            'company' => $request->string('company')->trim()->toString(),
            'description' => $request->string('description')->trim()->toString(),
            'source_url' => $request->filled('source_url') ? $request->string('source_url')->trim()->toString() : null,
            'source_name' => 'manual',
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        ExtractJobSkillsJob::dispatch($posting->id);

        /** @var JsonResponse $response */
        $response = (new JobPostingResource($posting->load('career')))
            ->response()
            ->setStatusCode(202);

        return $response;
    }
}
