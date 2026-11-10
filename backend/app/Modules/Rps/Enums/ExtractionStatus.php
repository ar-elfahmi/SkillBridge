<?php

declare(strict_types=1);

namespace App\Modules\Rps\Enums;

enum ExtractionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
