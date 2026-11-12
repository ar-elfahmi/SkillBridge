<?php

declare(strict_types=1);

namespace App\Modules\Matching\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\RpsDocument;
use App\Models\SkillMatch;
use App\Modules\Matching\Http\Requests\StoreAnalysisRequest;
use App\Modules\Matching\Http\Resources\AnalysisResource;
use App\Modules\Matching\Http\Resources\SkillMatchResource;
use App\Modules\Matching\Jobs\RunAnalysisJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AnalysisController extends Controller
{
    /**
     * Start a new skill gap analysis (202 Accepted).
     */
    public function store(StoreAnalysisRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $rps = RpsDocument::find($data['rps_document_id']);
        if ($rps === null || ! $rps->isOwnedBy($user)) {
            abort(404, 'Dokumen RPS tidak ditemukan.');
        }

        // Check RPS prerequisites: extraction completed & review confirmed
        if ($rps->extraction_status !== ExtractionStatus::Completed || $rps->review_status !== ReviewStatus::Confirmed) {
            return response()->json([
                'message' => 'Dokumen RPS belum selesai diekstrak atau belum dikonfirmasi.',
            ], 422);
        }

        // Check Job Postings prerequisites
        $jobPostings = JobPosting::visibleTo($user)
            ->whereIn('id', $data['job_posting_ids'])
            ->get();

        if ($jobPostings->count() !== count($data['job_posting_ids'])) {
            return response()->json([
                'message' => 'Satu atau lebih lowongan kerja tidak valid atau tidak memiliki akses.',
            ], 422);
        }

        foreach ($jobPostings as $posting) {
            if ($posting->career_id !== (int) $data['career_id']) {
                return response()->json([
                    'message' => 'Semua lowongan kerja harus sesuai dengan profesi yang dipilih.',
                ], 422);
            }

            if ($posting->extraction_status !== ExtractionStatus::Completed) {
                return response()->json([
                    'message' => 'Satu atau lebih lowongan kerja belum selesai diekstrak skill-nya.',
                ], 422);
            }
        }

        $analysis = Analysis::create([
            'user_id' => $user->id,
            'rps_document_id' => $rps->id,
            'career_id' => (int) $data['career_id'],
            'status' => AnalysisStatus::Queued,
        ]);

        $analysis->jobPostings()->sync($data['job_posting_ids']);

        RunAnalysisJob::dispatch($analysis->id);

        return response()->json([
            'data' => [
                'analysis_id' => $analysis->id,
                'status' => 'queued',
            ],
        ], 202);
    }

    /**
     * List analysis history for authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $analyses = Analysis::where('user_id', $request->user()->id)
            ->with(['career', 'rpsDocument.course'])
            ->latest('id')
            ->paginate(15);

        return AnalysisResource::collection($analyses);
    }

    /**
     * Get analysis detail and summary.
     */
    public function show(Request $request, Analysis $analysis): AnalysisResource
    {
        Gate::authorize('view', $analysis);

        $analysis->load(['career', 'rpsDocument.course']);

        return new AnalysisResource($analysis);
    }

    /**
     * Get detailed skill matches breakdown.
     */
    public function matches(Request $request, Analysis $analysis): JsonResponse
    {
        Gate::authorize('view', $analysis);

        $matches = $analysis->skillMatches()
            ->with(['skill', 'courseSkill'])
            ->orderBy('id')
            ->get();

        $allJobSkillIds = [];
        foreach ($matches as $match) {
            if (is_array($match->source_job_skill_ids)) {
                $allJobSkillIds = array_merge($allJobSkillIds, $match->source_job_skill_ids);
            }
        }

        $jobSkillsMap = [];
        if (! empty($allJobSkillIds)) {
            $jobSkillsMap = JobSkill::whereIn('id', array_unique($allJobSkillIds))
                ->with('jobPosting')
                ->get()
                ->keyBy('id')
                ->all();
        }

        $userSkillIds = [];
        if (Schema::hasTable('user_skills')) {
            $userSkillIds = DB::table('user_skills')
                ->where('user_id', $request->user()->id)
                ->pluck('skill_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        $items = $matches->map(function (SkillMatch $m) use ($jobSkillsMap, $userSkillIds) {
            return (new SkillMatchResource($m))
                ->withJobSkillsMap($jobSkillsMap)
                ->withUserSkillIds($userSkillIds);
        });

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * Delete an analysis.
     */
    public function destroy(Request $request, Analysis $analysis): Response
    {
        Gate::authorize('delete', $analysis);

        $analysis->delete();

        return response()->noContent();
    }
}
