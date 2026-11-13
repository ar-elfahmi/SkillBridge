<?php

declare(strict_types=1);

namespace App\Modules\Rps\Services;

use App\Models\Analysis;
use App\Models\Course;
use App\Models\RpsDocument;
use Illuminate\Database\Eloquent\Collection;

class RpsPipelineResult
{
    /**
     * @param  Collection<int, \App\Models\CourseSkill>  $courseSkills
     * @param  Collection<int, \App\Models\SkillMatch>  $skillMatches
     * @param  Collection<int, \App\Models\Recommendation>  $recommendations
     * @param  array<string, mixed>  $summary
     * @param  list<array{step: string, message: string, time_ms: int}>  $stepsLog
     */
    public function __construct(
        public readonly RpsDocument $document,
        public readonly Course $course,
        public readonly Collection $courseSkills,
        public readonly ?Analysis $analysis = null,
        public readonly Collection $skillMatches = new Collection,
        public readonly Collection $recommendations = new Collection,
        public readonly array $summary = [],
        public readonly array $stepsLog = [],
    ) {}
}
