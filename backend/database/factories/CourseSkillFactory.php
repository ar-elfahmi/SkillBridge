<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CompetencySourceType;
use App\Enums\SkillCategory;
use App\Models\CourseSkill;
use App\Models\RpsDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSkill>
 */
class CourseSkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rps_document_id' => RpsDocument::factory(),
            'skill_id' => null,
            'skill' => 'PHP MVC',
            'category' => SkillCategory::Concept,
            'competency' => 'Mampu menerapkan OOP pada pola MVC',
            'evidence' => 'Penerapan OOP pada pola MVC',
            'source_type' => CompetencySourceType::SubCpmk,
            'source_reference' => 'Sub-CPMK 9',
            'week' => 7,
            'page' => 3,
        ];
    }
}
