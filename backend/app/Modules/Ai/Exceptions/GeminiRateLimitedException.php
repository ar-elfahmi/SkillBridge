<?php

declare(strict_types=1);

namespace App\Modules\Ai\Exceptions;

/**
 * Thrown when the per-minute budget (internal counter or provider 429) is used up.
 * The caller should wait $retryAfterSeconds and try again; it is not a permanent failure.
 */
final class GeminiRateLimitedException extends GeminiException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Batas permintaan AI per menit tercapai. Permintaan akan dicoba lagi otomatis.');
    }
}
