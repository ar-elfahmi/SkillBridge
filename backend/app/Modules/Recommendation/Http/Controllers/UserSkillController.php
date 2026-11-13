<?php

declare(strict_types=1);

namespace App\Modules\Recommendation\Http\Controllers;

use App\Enums\SkillLevel;
use App\Models\UserSkill;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Recommendation\Http\Resources\UserSkillResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Skills the user reports learning outside the RPS. Informational only: adding
 * or removing one never changes classification or coverage.
 */
class UserSkillController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $skills = UserSkill::query()
            ->where('user_id', $request->user()->id)
            ->with('skill')
            ->orderByDesc('id')
            ->get();

        return UserSkillResource::collection($skills);
    }

    public function store(Request $request, SkillNormalizer $normalizer): JsonResponse
    {
        $validated = $request->validate([
            'skill' => ['required', 'string', 'max:255'],
            'level' => ['sometimes', 'string', Rule::enum(SkillLevel::class)],
            'analysis_id' => ['sometimes', 'integer'],
        ]);

        $raw = trim((string) $validated['skill']);
        $skill = $normalizer->normalize($raw);

        if ($skill === null) {
            return response()->json([
                'message' => 'Nama skill tidak valid.',
                'errors' => ['skill' => ['Nama skill tidak boleh kosong.']],
            ], 422);
        }

        $existing = UserSkill::query()
            ->where('user_id', $request->user()->id)
            ->where('skill_id', $skill->id)
            ->first();

        if ($existing !== null) {
            // Idempotent: return the row we already have.
            $existing->load('skill');

            /** @var JsonResponse $response */
            $response = (new UserSkillResource($existing))->response()->setStatusCode(200);

            return $response;
        }

        $userSkill = UserSkill::create([
            'user_id' => $request->user()->id,
            'skill_id' => $skill->id,
            'reported_as' => mb_strtolower($raw) === mb_strtolower($skill->canonical_name) ? null : $raw,
            'level' => $validated['level'] ?? SkillLevel::Intermediate->value,
        ]);

        $userSkill->load('skill');

        /** @var JsonResponse $response */
        $response = (new UserSkillResource($userSkill))->response()->setStatusCode(201);

        return $response;
    }

    public function destroy(Request $request, int $userSkill): JsonResponse|Response
    {
        $model = UserSkill::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($userSkill)
            ->first();

        // 404 rather than 403 so other users' rows are not leaked.
        if ($model === null) {
            abort(404);
        }

        $model->delete();

        return response()->noContent();
    }
}