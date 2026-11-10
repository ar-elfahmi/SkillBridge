<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SkillCategory;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'canonical_name' => fake()->unique()->words(2, true),
            'category' => SkillCategory::Concept,
            'aliases' => [],
        ];
    }
}
