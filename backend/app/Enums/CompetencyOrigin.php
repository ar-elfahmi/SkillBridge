<?php

declare(strict_types=1);

namespace App\Enums;

enum CompetencyOrigin: string
{
    case Ai = 'ai';
    // Added by the user during review.
    case User = 'user';
}
