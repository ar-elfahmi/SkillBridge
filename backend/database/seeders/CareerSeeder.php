<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Career;
use Illuminate\Database\Seeder;

/**
 * MVP careers. Idempotent: keyed by slug.
 */
class CareerSeeder extends Seeder
{
    /**
     * @return array<string, array{name: string, description: string}>
     */
    public static function careers(): array
    {
        return [
            'backend-developer' => [
                'name' => 'Backend Developer',
                'description' => 'Membangun dan memelihara logika sisi server, API, dan basis data aplikasi.',
            ],
            'frontend-developer' => [
                'name' => 'Frontend Developer',
                'description' => 'Membangun antarmuka web yang interaktif, responsif, dan mudah diakses.',
            ],
            'full-stack-developer' => [
                'name' => 'Full Stack Developer',
                'description' => 'Mengembangkan aplikasi web dari antarmuka hingga server dan basis data.',
            ],
            'data-analyst' => [
                'name' => 'Data Analyst',
                'description' => 'Mengolah, menganalisis, dan memvisualisasikan data untuk mendukung keputusan.',
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::careers() as $slug => $attributes) {
            Career::query()->updateOrCreate(['slug' => $slug], $attributes);
        }
    }
}
