<?php

declare(strict_types=1);

namespace App\Modules\Rps\Http\Controllers;

use App\Models\RpsDocument;
use App\Modules\Rps\Http\Requests\UpdateRpsMetadataRequest;
use App\Modules\Rps\Http\Resources\RpsDocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RpsDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $documents = RpsDocument::where('user_id', $request->user()->id)
            ->with('course')
            ->orderByDesc('id')
            ->get();

        return RpsDocumentResource::collection($documents);
    }

    public function show(Request $request, RpsDocument $document): RpsDocumentResource
    {
        Gate::authorize('view', $document);

        return new RpsDocumentResource($document->load('course'));
    }

    public function update(UpdateRpsMetadataRequest $request, RpsDocument $document): RpsDocumentResource
    {
        Gate::authorize('update', $document);

        $draft = $document->detected_metadata ?? [
            'code' => null,
            'name' => null,
            'credits' => null,
            'semester' => null,
            'study_program' => null,
        ];

        $payload = $request->all();
        foreach (['code', 'name', 'credits', 'semester', 'study_program'] as $field) {
            if (array_key_exists($field, $payload)) {
                $draft[$field] = $request->input($field);
            }
        }

        $document->update([
            'detected_metadata' => $draft,
            'error_message' => null,
        ]);

        return new RpsDocumentResource($document->fresh('course'));
    }

    public function destroy(Request $request, RpsDocument $document): Response|JsonResponse
    {
        Gate::authorize('delete', $document);

        $disk = Storage::disk('private');
        if ($document->file_path !== null && $document->file_path !== '' && $disk->exists($document->file_path)) {
            $deleted = $disk->delete($document->file_path);
            if (! $deleted) {
                return response()->json(['message' => 'Gagal menghapus berkas dokumen.'], 500);
            }
        }

        $document->delete();

        return response()->noContent();
    }
}
