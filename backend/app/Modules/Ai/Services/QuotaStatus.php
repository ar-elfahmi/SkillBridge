<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

enum QuotaStatus: string
{
    case Allowed = 'allowed';
    case Wait = 'wait';
    case DailyExhausted = 'daily_exhausted';
}
