<?php

declare(strict_types=1);

namespace App\Modules\Competency\Http\Controllers;

use App\Enums\CompetencyOrigin;
use App\Enums\CompetencyReviewStatus;
use App\Enums\CompetencySourceType;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\Course;
use App\Models\CourseSkill;
use App\Models\RpsDocument;
use App\Modules\Ai\EmbeddingService;
use App\Modules\Competency\Http\Resources\CourseSkillResource;
use App\Modules\Competency\Jobs\ExtractCompetenciesJob;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Enums\ReviewStatus;
use App\Modules\Rps\Http\Resources\RpsDocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class CompetencyController extends Controller
{
    public function extract(Request $request, RpsDocument $document): JsonResponse
    {
        Gate::authorize('update', $document);

        if ($document->parse_status !== ParseStatus::Parsed) {
            return response()->json([
                'message' => 'Dokumen RPS belum selesai diparsing atau memerlukan OCR.',
            ], 422);
        }

        $metadata = $document->detected_metadata ?? [];
        $reqCode = $request->input('code');
        $code = is_string($reqCode) && trim($reqCode) !== ''
            ? trim($reqCode)
            : (isset($metadata['code']) && is_string($metadata['code']) ? trim($metadata['code']) : '');

        if ($code === '' || preg_match('/^[.\s…_—-]+$/u', $code)) {
            return response()->json([
                'message' => 'Kode mata kuliah pada metadata wajib diisi sebelum ekstraksi kompetensi.',
                'errors' => [
                    'code' => ['Kode mata kuliah wajib diisi.'],
                ],
            ], 422);
        }

        if ($document->extraction_status === ExtractionStatus::Processing) {
            return response()->json([
                'message' => 'Proses ekstraksi kompetensi sedang berjalan.',
            ], 422);
        }

        DB::transaction(function () use ($document, $code, $metadata): void {
            $courseName = ! empty($metadata['name']) && is_string($metadata['name'])
                ? trim($metadata['name'])
                : $document->original_name;

            $course = Course::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $courseName,
                    'credits' => isset($metadata['credits']) && is_int($metadata['credits']) ? $metadata['credits'] : null,
                    'semester' => isset($metadata['semester']) && is_int($metadata['semester']) ? $metadata['semester'] : null,
                    'study_program' => ! empty($metadata['study_program']) && is_string($metadata['study_program'])
                        ? trim($metadata['study_program'])
                        : null,
                ]
            );

            $document->update([
                'course_id' => $course->id,
                'metadata_confirmed_at' => $document->metadata_confirmed_at ?? now(),
                'extraction_status' => ExtractionStatus::Processing,
                'error_message' => null,
            ]);
        });

        ExtractCompetenciesJob::dispatch($document->id);

        /** @var JsonResponse $response */
        $response = (new RpsDocumentResource($document->fresh('course')))
            ->response()
            ->setStatusCode(202);

        return $response;
    }

    public function index(Request $request, RpsDocument $document): AnonymousResourceCollection
    {
        Gate::authorize('view', $document);

        $competencies = $document->courseSkills()
            ->with('skillEntry')
            ->orderBy('id')
            ->get();

        return CourseSkillResource::collection($competencies);
    }

    public function store(
        Request $request,
        RpsDocument $document,
        SkillNormalizer $skillNormalizer,
        EmbeddingService $embeddingService,
    ): JsonResponse {
        Gate::authorize('update', $document);

        if ($document->extraction_status !== ExtractionStatus::Completed) {
            return response()->json([
                'message' => 'Ekstraksi kompetensi RPS belum selesai.',
            ], 422);
        }

        $allowedKeys = ['skill', 'competency', 'category', 'review_status'];
        $extraKeys = array_diff(array_keys($request->all()), $allowedKeys);
        if ($extraKeys !== []) {
            $errors = [];
            foreach ($extraKeys as $key) {
                $errors[$key] = ["Field {$key} tidak diizinkan."];
            }

            return response()->json([
                'message' => 'Terdapat field yang tidak diizinkan.',
                'errors' => $errors,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'skill' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        $fail('Nama skill wajib diisi.');
                    }
                },
            ],
            'competency' => [
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        $fail('Deskripsi kompetensi wajib diisi.');
                    }
                },
            ],
            'category' => ['sometimes', 'nullable', 'string', Rule::enum(SkillCategory::class)],
            'review_status' => ['sometimes', 'string', 'in:pending,confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data kompetensi tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $skillName = trim((string) $validated['skill']);
        $competencyText = trim((string) $validated['competency']);
        $categoryInput = $validated['category'] ?? null;

        $normalizedSkill = $skillNormalizer->normalize($skillName, $categoryInput);
        $resolvedCategory = $normalizedSkill?->category
            ?? ($categoryInput !== null ? SkillCategory::from($categoryInput) : null);

        $reviewStatus = isset($validated['review_status'])
            ? CompetencyReviewStatus::from((string) $validated['review_status'])
            : CompetencyReviewStatus::Pending;

        $courseSkill = CourseSkill::create([
            'rps_document_id' => $document->id,
            'skill_id' => $normalizedSkill?->id,
            'skill' => $skillName,
            'category' => $resolvedCategory,
            'competency' => $competencyText,
            'evidence' => null,
            'source_type' => CompetencySourceType::User,
            'origin' => CompetencyOrigin::User,
            'review_status' => $reviewStatus,
            'user_edited' => false,
            'source_reference' => null,
            'week' => null,
            'page' => null,
            'verification_status' => VerificationStatus::Unverified,
        ]);

        try {
            $embeddingService->embedCourseSkills(collect([$courseSkill->load('skillEntry')]), $document);
        } catch (Throwable) {
            // Embedding is best-effort during manual creation if API is unreachable
        }

        /** @var JsonResponse $response */
        $response = (new CourseSkillResource($courseSkill->fresh('skillEntry')))
            ->response()
            ->setStatusCode(201);

        return $response;
    }

    public function update(
        Request $request,
        RpsDocument $document,
        CourseSkill $competency,
        SkillNormalizer $skillNormalizer,
        EmbeddingService $embeddingService,
    ): JsonResponse {
        Gate::authorize('update', $document);

        if ($competency->rps_document_id !== $document->id) {
            abort(404);
        }

        $allowedKeys = ['review_status', 'skill', 'competency'];
        $inputKeys = array_keys($request->all());

        if ($inputKeys === []) {
            return response()->json([
                'message' => 'Tidak ada perubahan yang dikirim.',
                'errors' => [
                    'payload' => ['Minimal satu field (review_status, skill, atau competency) wajib dikirim.'],
                ],
            ], 422);
        }

        $extraKeys = array_diff($inputKeys, $allowedKeys);
        if ($extraKeys !== []) {
            $errors = [];
            foreach ($extraKeys as $key) {
                $errors[$key] = ["Field {$key} tidak dapat diubah."];
            }

            return response()->json([
                'message' => 'Bukti kutipan atau atribut internal tidak dapat diubah.',
                'errors' => $errors,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'review_status' => ['sometimes', 'required', 'string', 'in:confirmed,rejected'],
            'skill' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        $fail('Nama skill tidak boleh kosong.');
                    }
                },
            ],
            'competency' => [
                'sometimes',
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        $fail('Deskripsi kompetensi tidak boleh kosong.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data pembaruan kompetensi tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $newSkill = array_key_exists('skill', $validated) ? trim((string) $validated['skill']) : $competency->skill;
        $newCompetency = array_key_exists('competency', $validated) ? trim((string) $validated['competency']) : $competency->competency;
        $textChanged = ($newSkill !== $competency->skill) || ($newCompetency !== $competency->competency);

        $updates = [];

        if (array_key_exists('review_status', $validated)) {
            $updates['review_status'] = CompetencyReviewStatus::from((string) $validated['review_status']);
        }

        if ($textChanged) {
            $normalized = $skillNormalizer->normalize($newSkill, $competency->category);
            $updates['skill'] = $newSkill;
            $updates['skill_id'] = $normalized?->id;
            $updates['category'] = $normalized?->category ?? $competency->category;
            $updates['competency'] = $newCompetency;
            $updates['user_edited'] = true;
        }

        if ($updates !== []) {
            $competency->update($updates);
        }

        if ($textChanged) {
            try {
                $embeddingService->embedCourseSkills(collect([$competency->fresh('skillEntry')]), $document);
            } catch (Throwable) {
                // Keep updated competency even if embedding service fails
            }
        }

        /** @var JsonResponse $response */
        $response = (new CourseSkillResource($competency->fresh('skillEntry')))
            ->response()
            ->setStatusCode(200);

        return $response;
    }

    public function destroy(Request $request, RpsDocument $document, CourseSkill $competency): JsonResponse|Response
    {
        Gate::authorize('update', $document);

        if ($competency->rps_document_id !== $document->id) {
            abort(404);
        }

        if ($competency->origin !== CompetencyOrigin::User) {
            return response()->json([
                'message' => 'Kompetensi hasil ekstraksi AI tidak dapat dihapus; gunakan status review rejected.',
            ], 422);
        }

        $competency->delete();

        return response()->noContent();
    }

    public function confirmReview(Request $request, RpsDocument $document): JsonResponse
    {
        Gate::authorize('update', $document);

        if ($document->extraction_status !== ExtractionStatus::Completed) {
            return response()->json([
                'message' => 'Ekstraksi kompetensi belum selesai. Tunggu hingga proses ekstraksi selesai sebelum melakukan konfirmasi.',
            ], 422);
        }

        if ($request->boolean('confirm_all_pending')) {
            $document->courseSkills()
                ->where('review_status', CompetencyReviewStatus::Pending)
                ->update(['review_status' => CompetencyReviewStatus::Confirmed]);
        }

        $pendingCount = $document->courseSkills()
            ->where('review_status', CompetencyReviewStatus::Pending)
            ->count();

        if ($pendingCount > 0) {
            return response()->json([
                'message' => "Masih ada {$pendingCount} kompetensi yang belum ditinjau. Silakan konfirmasi atau tolak semua kompetensi terlebih dahulu.",
            ], 422);
        }

        $document->update([
            'review_status' => ReviewStatus::Confirmed,
            'reviewed_at' => now(),
        ]);

        /** @var JsonResponse $response */
        $response = (new RpsDocumentResource($document->fresh('course')))
            ->response()
            ->setStatusCode(200);

        return $response;
    }
}
