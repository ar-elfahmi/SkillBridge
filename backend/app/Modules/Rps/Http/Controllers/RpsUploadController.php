<?php

declare(strict_types=1);

namespace App\Modules\Rps\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RpsDocument;
use App\Models\User;
use App\Modules\Rps\Actions\StoreRpsDocument;
use App\Modules\Rps\Http\Requests\UploadRpsRequest;
use App\Modules\Rps\Http\Resources\RpsDocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class RpsUploadController extends Controller
{
    public function __invoke(UploadRpsRequest $request, StoreRpsDocument $store): JsonResponse
    {
        Gate::authorize('create', RpsDocument::class);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $document = $store->execute($user, $file);

        return (new RpsDocumentResource($document))->response()->setStatusCode(202);
    }
}
