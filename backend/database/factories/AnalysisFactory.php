<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Analysis;
use App\Models\Career;
use App\Models\RpsDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Analysis>
 */
class AnalysisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rps_document_id' => RpsDocument::factory(),
            // Same owner as the RPS unless overridden.
            'user_id' => fn (array $attributes) => RpsDocument::findOrFail($attributes['rps_document_id'])->user_id,
            'career_id' => Career::factory(),
        ];
    }
}
