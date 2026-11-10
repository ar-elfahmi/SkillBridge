<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Industry\Services\JobPostingImporter;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample curated postings labelled "data contoh" (fictional companies, no
 * source URLs). For demo/development only, not validated industry data.
 * Idempotent via JobPostingImporter.
 */
class SampleJobPostingSeeder extends Seeder
{
    public const DATASET_VERSION = 'sample-v1';

    public function run(JobPostingImporter $importer): void
    {
        $this->call(CareerSeeder::class);

        $rows = array_map(
            fn (array $row) => $row + ['dataset_version' => self::DATASET_VERSION],
            $importer->readFile(database_path('data/sample_jobs.json')),
        );

        $errors = $importer->validate($rows);
        if ($errors !== []) {
            throw new RuntimeException('sample_jobs.json tidak valid: '.json_encode($errors));
        }

        $importer->import($rows);
    }
}
