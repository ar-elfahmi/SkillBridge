<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Coverage of an industry skill in the analyzed RPS. "missing" means not found
 * sufficiently in the RPS, not that the student lacks the skill.
 */
enum MatchClassification: string
{
    case Covered = 'covered';
    case Partial = 'partial';
    case Missing = 'missing';
}
