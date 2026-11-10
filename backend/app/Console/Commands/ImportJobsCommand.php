<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Industry\Services\JobPostingImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportJobsCommand extends Command
{
    protected $signature = 'skillbridge:import-jobs
        {file : Path ke file .csv atau .json}
        {--dataset-version= : Isi dataset_version untuk baris yang tidak menyebutkannya}
        {--dry-run : Validasi saja, tanpa menyimpan}';

    protected $description = 'Impor lowongan terkurasi (created_by = null) dari CSV/JSON';

    public function handle(JobPostingImporter $importer): int
    {
        try {
            $rows = $importer->readFile((string) $this->argument('file'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('File tidak berisi lowongan.');

            return self::SUCCESS;
        }

        $version = $this->option('dataset-version');
        if (is_string($version) && $version !== '') {
            $rows = array_map(function (array $row) use ($version): array {
                $row['dataset_version'] ??= $version;

                return $row;
            }, $rows);
        }

        $errors = $importer->validate($rows);

        if ($errors !== []) {
            foreach ($errors as $rowNumber => $messages) {
                foreach ($messages as $message) {
                    $this->error("Baris {$rowNumber}: {$message}");
                }
            }
            $this->error(count($errors).' baris tidak valid. Tidak ada yang diimpor.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info(count($rows).' baris valid (dry run, tidak disimpan).');

            return self::SUCCESS;
        }

        ['created' => $created, 'updated' => $updated] = $importer->import($rows);

        $this->info("Selesai: {$created} lowongan baru, {$updated} diperbarui.");
        // Skill extraction (ExtractJobSkillsJob, T5.3) is not dispatched yet; postings stay "pending".

        return self::SUCCESS;
    }
}
