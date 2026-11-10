<?php

declare(strict_types=1);

namespace App\Modules\Rps\Jobs;

use App\Models\RpsDocument;
use App\Modules\Rps\Enums\ParseStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Extracts per-page text into rps_pages. Parsing logic lands in T2.3;
 * this class fixes the queue contract (tries, backoff, failed()).
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

    public function handle(): void
    {
        // Implemented in T2.3.
    }

    public function failed(?Throwable $exception): void
    {
        RpsDocument::whereKey($this->rpsDocumentId)->update([
            'parse_status' => ParseStatus::Failed,
            'error_message' => 'Gagal membaca isi PDF. Coba unggah ulang.',
        ]);
    }
}
