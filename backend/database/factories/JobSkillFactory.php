<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSkill>
 */
class JobSkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'skill_id' => Skill::factory(),
            'skill' => 'Laravel',
            'requirement_text' => 'Membangun REST API dengan Laravel',
            'evidence' => 'Membangun REST API dengan Laravel',
        ];
    }
}
