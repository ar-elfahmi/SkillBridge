<?php

declare(strict_types=1);

namespace App\Modules\Rps\Jobs;

use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Modules\Rps\Enums\ParseStatus;
use App\Modules\Rps\Exceptions\InvalidPdfException;
use App\Modules\Rps\Services\PdfInspector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Extracts per-page text into rps_pages and sets parse_status
 * (parsed | needs_ocr | failed). Idempotent: re-running replaces pages,
 * and a document that is already parsed / needs_ocr is skipped.
 * Never logs document text.
 */
class ParseRpsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $rpsDocumentId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->rpsDocumentId))->releaseAfter(30)->expireAfter(180)];
    }

    public function handle(PdfInspector $inspector): void
    {
        $document = RpsDocument::find($this->rpsDocumentId);

        // Deleted meanwhile, or already finished by an earlier attempt.
        if ($document === null || in_array($document->parse_status, [ParseStatus::Parsed, ParseStatus::NeedsOcr], true)) {
            return;
        }

        $disk = Storage::disk('private');

        if (! $disk->exists($document->file_path)) {
            $this->markFailed($document, 'Berkas RPS tidak ditemukan. Silakan unggah ulang.');

            return;
        }

        $document->update(['parse_status' => ParseStatus::Parsing, 'error_message' => null]);

        try {
            $texts = $inspector->pageTexts($disk->path($document->file_path));
        } catch (InvalidPdfException) {
            // Deterministic: retrying will not help.
            $this->markFailed($document, 'Berkas PDF tidak dapat dibaca. Pastikan berkas tidak rusak atau terkunci.');

            return;
        }

        DB::transaction(function () use ($document, $texts): void {
            RpsPage::where('rps_document_id', $document->id)->delete();

            $now = now();
            RpsPage::insert(array_map(fn (string $text, int $i) => [
                'rps_document_id' => $document->id,
                'page_number' => $i + 1,
                'text' => $text,
                'created_at' => $now,
                'updated_at' => $now,
            ], $texts, array_keys($texts)));

            $needsOcr = $this->looksScanned($texts);

            $document->update([
                'page_count' => count($texts),
                'parse_status' => $needsOcr ? ParseStatus::NeedsOcr : ParseStatus::Parsed,
                'error_message' => $needsOcr
                    ? 'PDF tampak hasil scan (teks tidak terbaca). Unggah PDF digital yang teksnya bisa dipilih.'
                    : null,
            ]);
        });

        // Next step (metadata extraction, T2.5) is dispatched by Modules/Ai once available.
    }

    public function failed(?Throwable $exception): void
    {
        $document = RpsDocument::find($this->rpsDocumentId);

        if ($document !== null) {
            $this->markFailed($document, 'Gagal membaca isi PDF. Coba unggah ulang.');
        }
    }

    /**
     * @param  list<string>  $texts
     */
    private function looksScanned(array $texts): bool
    {
        $minAverage = (int) config('skillbridge.upload.min_chars_per_page');
        $total = array_sum(array_map(fn (string $t) => mb_strlen((string) preg_replace('/\s+/u', '', $t)), $texts));

        return $total / max(count($texts), 1) < $minAverage;
    }

    private function markFailed(RpsDocument $document, string $message): void
    {
        $document->update(['parse_status' => ParseStatus::Failed, 'error_message' => $message]);
    }
}
