<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RpsDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RpsDocument>
 */
class RpsDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hash = hash('sha256', fake()->unique()->uuid());

        return [
            'user_id' => User::factory(),
            'course_id' => null,
            'original_name' => 'rps-'.fake()->slug(2).'.pdf',
            'file_path' => 'rps/'.$hash.'.pdf',
            'file_hash' => $hash,
            'file_size' => fake()->numberBetween(10_000, 2_000_000),
            'page_count' => null,
        ];
    }
}
