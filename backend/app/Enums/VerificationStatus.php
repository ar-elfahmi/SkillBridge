<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an evidence quote was found in the source text.
 */
enum VerificationStatus: string
{
    case Verified = 'verified';
    case Unverified = 'unverified';
}
