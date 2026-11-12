<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Safe to run repeatedly.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password'],
        );

        $seeders = [SkillSeeder::class, CareerSeeder::class, SampleJobPostingSeeder::class];

        if (! app()->runningUnitTests() && (bool) env('SEED_PARTS_JOBS', true) && PartsJobPostingSeeder::resolvePartsDirectory() !== null) {
            $seeders[] = PartsJobPostingSeeder::class;
        }

        $this->call($seeders);
    }
}
