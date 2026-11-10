<?php

declare(strict_types=1);

namespace App\Modules\Rps\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class TooManyPagesException extends RuntimeException
{
    public function __construct(public readonly int $maxPages)
    {
        parent::__construct();
    }

    public function render(): JsonResponse
    {
        $message = "Jumlah halaman PDF melebihi batas {$this->maxPages} halaman.";

        return response()->json([
            'message' => $message,
            'errors' => ['file' => [$message]],
        ], 422);
    }
}
