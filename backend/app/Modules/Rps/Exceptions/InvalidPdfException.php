<?php

declare(strict_types=1);

namespace App\Modules\Rps\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvalidPdfException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Berkas PDF tidak dapat dibaca. Pastikan berkas tidak rusak atau terkunci.',
            'errors' => ['file' => ['Berkas PDF tidak dapat dibaca.']],
        ], 422);
    }
}
