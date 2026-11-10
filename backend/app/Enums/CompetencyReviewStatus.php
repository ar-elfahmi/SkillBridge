<?php

declare(strict_types=1);

namespace App\Enums;

enum CompetencyReviewStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    // Kept for restore, excluded from matching.
    case Rejected = 'rejected';
}
