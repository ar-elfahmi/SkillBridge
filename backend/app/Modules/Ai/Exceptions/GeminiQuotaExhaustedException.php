<?php

declare(strict_types=1);

namespace App\Modules\Ai\Exceptions;

use Carbon\CarbonImmutable;

/**
 * Thrown when the daily request budget of a model bucket is used up.
 * Retrying before $resetsAt is pointless, so jobs fail immediately with code "quota_exhausted".
 */
final class GeminiQuotaExhaustedException extends GeminiException
{
    public const CODE = 'quota_exhausted';

    public function __construct(
        public readonly CarbonImmutable $resetsAt,
        public readonly string $bucket = 'flash',
    ) {
        parent::__construct(self::messageFor($resetsAt));
    }

    /**
     * Safe, user-facing text (Indonesian). The reset time is shown in WIB.
     */
    public static function messageFor(CarbonImmutable $resetsAt): string
    {
        $local = $resetsAt->setTimezone('Asia/Jakarta')->locale('id');

        return 'Kuota AI harian habis. Coba lagi setelah '.$local->translatedFormat('j F Y').' pukul '.$local->format('H.i').' WIB.';
    }
}
