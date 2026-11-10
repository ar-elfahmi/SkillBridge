<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Career;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default state is a curated posting (created_by = null).
 *
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'career_id' => Career::factory(),
            'created_by' => null,
            'title' => 'Backend Developer',
            'company' => 'PT Contoh',
            'description' => 'Membangun REST API dengan Laravel dan PostgreSQL.',
            'qualifications' => 'Menguasai Git dan Docker.',
            'source_name' => 'data contoh',
            'source_url' => null,
            'collected_at' => null,
            'dataset_version' => null,
        ];
    }

    public function manual(?User $owner = null): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $owner ?? User::factory(),
            'source_name' => null,
        ]);
    }
}
