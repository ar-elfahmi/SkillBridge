<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecommendationPriority;
use App\Models\Recommendation;
use App\Models\SkillMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'skill_match_id' => SkillMatch::factory(),
            'analysis_id' => fn (array $attributes) => SkillMatch::findOrFail($attributes['skill_match_id'])->analysis_id,
            'title' => 'Docker Fundamentals',
            'reason' => 'Belum ditemukan dalam RPS yang dianalisis dan diminta pada 2 dari 2 lowongan.',
            'priority' => RecommendationPriority::High,
            'details' => ['topics' => ['Image & container', 'Dockerfile'], 'learning_keywords' => ['docker getting started']],
        ];
    }
}
