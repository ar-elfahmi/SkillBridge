<?php

declare(strict_types=1);

namespace App\Modules\Rps\Enums;

enum ParseStatus: string
{
    case Pending = 'pending';
    case Parsing = 'parsing';
    case Parsed = 'parsed';
    case Failed = 'failed';
    case NeedsOcr = 'needs_ocr';
}
