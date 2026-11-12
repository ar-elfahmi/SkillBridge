<?php

declare(strict_types=1);

namespace App\Modules\Ai\Exceptions;

use RuntimeException;

final class GeminiException extends RuntimeException
{
    public function __construct(string $message = 'Layanan AI gagal memproses permintaan. Silakan coba lagi.')
    {
        parent::__construct($message);
    }
}
