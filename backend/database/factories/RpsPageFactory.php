<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RpsDocument;
use App\Models\RpsPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RpsPage>
 */
class RpsPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rps_document_id' => RpsDocument::factory(),
            'page_number' => 1,
            'text' => fake()->paragraph(),
        ];
    }
}
