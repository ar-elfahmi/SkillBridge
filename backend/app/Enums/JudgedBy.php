<?php

declare(strict_types=1);

namespace App\Enums;

enum JudgedBy: string
{
    // Decided by rules alone (e.g. best score below SIM_FLOOR), no LLM call.
    case Rule = 'rule';
    case Llm = 'llm';
}
