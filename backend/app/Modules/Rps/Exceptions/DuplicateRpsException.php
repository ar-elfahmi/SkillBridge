<?php

declare(strict_types=1);

namespace App\Modules\Rps\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class DuplicateRpsException extends RuntimeException
{
    public function __construct(public readonly int $existingId)
    {
        parent::__construct();
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'RPS ini sudah pernah Anda unggah.',
            'existing_id' => $this->existingId,
        ], 409);
    }
}
