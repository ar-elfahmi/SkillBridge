<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Industry\Services\JobPostingImporter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds curated IT job postings from split parts JSON files (all-2026-11-10-part*.json).
 * Idempotent via JobPostingImporter updateOrCreate logic.
 */
class PartsJobPostingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Resolves the directory path containing the parts JSON files.
     */
    public static function resolvePartsDirectory(): ?string
    {
        $candidates = array_filter([
            env('PARTS_DATA_PATH'),
            base_path('../parts'),
            base_path('parts'),
            database_path('data/parts'),
            dirname(base_path()).DIRECTORY_SEPARATOR.'parts',
        ]);

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }

    /**
     * Run the parts job posting seeder.
     *
     * @param  JobPostingImporter  $importer
     * @param  string|null  $partsDir  Optional custom directory path (for testing or specific location)
     * @return array{created: int, updated: int, files: int}
     */
    public function run(JobPostingImporter $importer, ?string $partsDir = null): array
    {
        $this->call(CareerSeeder::class);

        $dir = $partsDir ?? self::resolvePartsDirectory();
        if ($dir === null || ! is_dir($dir)) {
            throw new RuntimeException("Folder parts tidak ditemukan. Pastikan folder 'parts' ada di root project atau atur PARTS_DATA_PATH.");
        }

        $files = glob($dir.DIRECTORY_SEPARATOR.'*.json') ?: [];
        sort($files);

        if ($files === []) {
            throw new RuntimeException("Tidak ada file .json ditemukan di folder parts: {$dir}");
        }

        $totalCreated = 0;
        $totalUpdated = 0;

        foreach ($files as $file) {
            $filename = basename($file);
            $this->command?->info("Memproses {$filename}...");

            $rows = $importer->readFile($file);
            if ($rows === []) {
                $this->command?->warn("{$filename} kosong, dilewati.");
                continue;
            }

            $errors = $importer->validate($rows);
            if ($errors !== []) {
                throw new RuntimeException(sprintf(
                    'File %s berisi %d baris tidak valid: %s',
                    $filename,
                    count($errors),
                    json_encode(array_slice($errors, 0, 5, true))
                ));
            }

            ['created' => $created, 'updated' => $updated] = $importer->import($rows);
            $totalCreated += $created;
            $totalUpdated += $updated;

            $this->command?->info(sprintf(
                '[%s] %d lowongan baru, %d diperbarui.',
                $filename,
                $created,
                $updated
            ));
        }

        $this->command?->info(sprintf(
            'Selesai seeder parts: total %d lowongan baru, %d diperbarui dari %d file.',
            $totalCreated,
            $totalUpdated,
            count($files)
        ));

        return [
            'created' => $totalCreated,
            'updated' => $totalUpdated,
            'files' => count($files),
        ];
    }
}
