<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->bothify('########??#'),
            'name' => fake()->words(2, true),
            'credits' => fake()->numberBetween(2, 4),
            'semester' => fake()->numberBetween(1, 8),
            'study_program' => 'Teknik Informatika',
        ];
    }
}
