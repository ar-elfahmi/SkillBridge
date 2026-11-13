<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Self-reported familiarity level. Informational only: it never changes
 * classification or coverage, and the UI must not present it as mastery.
 */
enum SkillLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
}