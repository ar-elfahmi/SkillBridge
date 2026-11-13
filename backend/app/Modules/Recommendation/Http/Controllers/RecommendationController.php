<?php

declare(strict_types=1);

namespace App\Modules\Recommendation\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\MatchClassification;
use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Recommendation;
use App\Modules\Recommendation\Http\Resources\RecommendationResource;
use App\Modules\Recommendation\Jobs\GenerateRecommendationsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class RecommendationController extends Controller
{
    /**
     * Get learning recommendations for a completed analysis.
     */
    /**
     * Regenerate recommendations so newly declared personal skills are excluded.
     * Classification and coverage are not recomputed.
     */
    public function refresh(Request $request, Analysis $analysis): JsonResponse
    {
        Gate::authorize('view', $analysis);

        if ($analysis->status !== AnalysisStatus::Completed) {
            return response()->json([
                'message' => 'Analisis belum selesai diproses.',
            ], 422);
        }

        GenerateRecommendationsJob::dispatch($analysis->id);

        return response()->json([
            'message' => 'Rekomendasi sedang diperbarui.',
            'analysis_id' => $analysis->id,
        ], 202);
    }

    public function index(Request $request, Analysis $analysis): JsonResponse
    {
        Gate::authorize('view', $analysis);

        if ($analysis->status !== AnalysisStatus::Completed) {
            return response()->json([
                'message' => 'Analisis belum selesai diproses.',
            ], 422);
        }

        $recommendations = $analysis->recommendations()
            ->with(['skillMatch.skill'])
            ->get();

        // If recommendations are empty and there are partial/missing matches, generate on demand
        if ($recommendations->isEmpty()) {
            $hasUncovered = $analysis->skillMatches()
                ->whereIn('classification', [MatchClassification::Missing->value, MatchClassification::Partial->value])
                ->exists();

            if ($hasUncovered) {
                $job = app(GenerateRecommendationsJob::class, ['analysisId' => $analysis->id]);
                app()->call([$job, 'handle']);

                $recommendations = $analysis->recommendations()
                    ->with(['skillMatch.skill'])
                    ->get();
            }
        }

        $sorted = $recommendations->sort(function (Recommendation $a, Recommendation $b): int {
            $weightA = $a->priority->weight();
            $weightB = $b->priority->weight();

            if ($weightA !== $weightB) {
                return $weightB <=> $weightA;
            }

            return $a->id <=> $b->id;
        })->values();

        $selfDeclaredSkills = [];
        if (Schema::hasTable('user_skills')) {
            $userSkillIds = DB::table('user_skills')
                ->where('user_id', $request->user()->id)
                ->pluck('skill_id')
                ->map(fn ($id): int => (int) $id)
                ->toArray();

            $selfDeclaredSkills = $analysis->skillMatches()
                ->whereIn('classification', [MatchClassification::Missing->value, MatchClassification::Partial->value])
                ->whereIn('skill_id', $userSkillIds)
                ->with('skill')
                ->get()
                ->map(fn ($m): array => [
                    'skill' => $m->skill?->canonical_name ?? '',
                    'classification' => $m->classification->value,
                    'label' => 'Dipelajari mandiri (dilaporkan pengguna)',
                ])
                ->values()
                ->toArray();
        }

        return response()->json([
            'data' => RecommendationResource::collection($sorted),
            'meta' => [
                'self_declared_skills' => $selfDeclaredSkills,
            ],
        ]);
    }
}
