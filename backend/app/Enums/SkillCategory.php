<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Shared by skills, course_skills and job_skills.
 */
enum SkillCategory: string
{
    case Language = 'language';
    case Framework = 'framework';
    case Tool = 'tool';
    case Concept = 'concept';
    case Practice = 'practice';
    case Other = 'other';

    /**
     * Technology-specific categories: matching caps these at "partial" when the
     * technology name is absent from the evidence (docs/AI_PIPELINE.md).
     */
    public function isTechnologySpecific(): bool
    {
        return in_array($this, [self::Language, self::Framework, self::Tool], true);
    }
}
