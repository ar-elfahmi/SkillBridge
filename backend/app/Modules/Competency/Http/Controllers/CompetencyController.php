<?php

declare(strict_types=1);

namespace App\Modules\Competency\Http\Controllers;

use App\Models\Course;
use App\Models\RpsDocument;
use App\Modules\Competency\Http\Resources\CourseSkillResource;
use App\Modules\Competency\Jobs\ExtractCompetenciesJob;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Http\Resources\RpsDocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
        $code = isset($metadata['code']) && is_string($metadata['code']) ? trim($metadata['code']) : '';
        if ($code === '') {
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
}
