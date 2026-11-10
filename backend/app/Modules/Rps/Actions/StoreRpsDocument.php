<?php

declare(strict_types=1);

namespace App\Modules\Rps\Actions;

use App\Models\RpsDocument;
use App\Models\User;
use App\Modules\Rps\Exceptions\DuplicateRpsException;
use App\Modules\Rps\Exceptions\TooManyPagesException;
use App\Modules\Rps\Jobs\ParseRpsJob;
use App\Modules\Rps\Services\PdfInspector;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreRpsDocument
{
    public function __construct(private readonly PdfInspector $inspector) {}

    public function execute(User $user, UploadedFile $file): RpsDocument
    {
        $realPath = (string) $file->getRealPath();
        $hash = (string) hash_file('sha256', $realPath);

        $this->guardDuplicate($user, $hash);

        $pageCount = $this->inspector->pageCount($realPath);
        $maxPages = (int) config('skillbridge.upload.max_pages');

        if ($pageCount > $maxPages) {
            throw new TooManyPagesException($maxPages);
        }

        $disk = Storage::disk('private');
        $path = "rps/{$user->id}/{$hash}.pdf";
        $disk->putFileAs("rps/{$user->id}", $file, "{$hash}.pdf");

        try {
            $document = RpsDocument::create([
                'user_id' => $user->id,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'file_path' => $path,
                'file_hash' => $hash,
                'file_size' => (int) $file->getSize(),
                'page_count' => $pageCount,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Concurrent upload of the same file won the race; keep its stored file.
            $this->guardDuplicate($user, $hash);

            throw new \LogicException('Unique violation without an existing document.');
        }

        ParseRpsJob::dispatch($document->id);

        return $document;
    }

    private function guardDuplicate(User $user, string $hash): void
    {
        $existingId = RpsDocument::query()
            ->where('user_id', $user->id)
            ->where('file_hash', $hash)
            ->value('id');

        if ($existingId !== null) {
            throw new DuplicateRpsException((int) $existingId);
        }
    }
}
