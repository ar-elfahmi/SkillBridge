<?php

declare(strict_types=1);

namespace App\Modules\Rps\Enums;

enum ReviewStatus: string
{
    case Unreviewed = 'unreviewed';
    // Required before an analysis can be created.
    case Confirmed = 'confirmed';
}
