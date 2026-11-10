<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JudgedBy;
use App\Enums\MatchClassification;
use App\Models\Analysis;
use App\Models\Skill;
use App\Models\SkillMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a rule-judged "missing" match (no RPS competency).
 *
 * @extends Factory<SkillMatch>
 */
class SkillMatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'analysis_id' => Analysis::factory(),
            'skill_id' => Skill::factory(),
            'demand_count' => 1,
            'source_job_skill_ids' => [],
            'course_skill_id' => null,
            'similarity_score' => null,
            'classification' => MatchClassification::Missing,
            'reason' => 'Belum ditemukan dalam RPS yang dianalisis.',
            'evidence' => null,
            'candidates' => [],
            'judged_by' => JudgedBy::Rule,
        ];
    }
}
