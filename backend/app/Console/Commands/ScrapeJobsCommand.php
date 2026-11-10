<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Industry\Scraping\JobScraper;
use App\Modules\Industry\Scraping\JobSource;
use App\Modules\Industry\Scraping\Sources\ArbeitnowSource;
use App\Modules\Industry\Scraping\Sources\RemotiveSource;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Collects postings from an allowed public API into a JSON file for human
 * review. Never writes to the database: load the file with skillbridge:import-jobs.
 */
class ScrapeJobsCommand extends Command
{
    protected $signature = 'skillbridge:scrape-jobs
        {source : Sumber: remotive | arbeitnow}
        {--limit=200 : Jumlah maksimum lowongan yang disimpan}
        {--out= : Path file JSON keluaran (default storage/app/private/scraped/...)}
        {--dataset-version= : Isi dataset_version (default <source>-<tanggal>)}';

    protected $description = 'Kumpulkan lowongan dari API publik ke file JSON untuk ditinjau lalu diimpor';

    /** @var array<string, class-string<JobSource>> */
    private const SOURCES = [
        'remotive' => RemotiveSource::class,
        'arbeitnow' => ArbeitnowSource::class,
    ];

    public function handle(JobScraper $scraper): int
    {
        $key = strtolower((string) $this->argument('source'));

        if (! isset(self::SOURCES[$key])) {
            $this->error('Sumber tidak dikenal. Pilihan: '.implode(', ', array_keys(self::SOURCES)).'.');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        if ($limit < 1) {
            $this->error('--limit harus >= 1.');

            return self::FAILURE;
        }

        $today = now()->format('Y-m-d');
        $version = (string) ($this->option('dataset-version') ?: "{$key}-{$today}");
        $source = app(self::SOURCES[$key]);

        $this->info("Mengambil lowongan dari {$source->name()}...");

        try {
            ['rows' => $rows, 'skipped' => $skipped] = $scraper->collect($source, $limit, $version, $today);
        } catch (RequestException|ConnectionException $e) {
            $this->error("Gagal mengambil data dari {$source->name()}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $out = (string) ($this->option('out')
            ?: config('skillbridge.scraping.output_dir')."/{$version}.json");

        if (! is_dir(dirname($out)) && ! mkdir(dirname($out), 0750, true) && ! is_dir(dirname($out))) {
            $this->error("Tidak bisa membuat folder untuk {$out}.");

            return self::FAILURE;
        }

        file_put_contents($out, json_encode([
            '_note' => "Dikumpulkan otomatis dari {$source->name()} pada {$today}. Tinjau sebelum diimpor. "
                .'Atribusi wajib: source_name + source_url dipertahankan.',
            'jobs' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $this->info(count($rows)." lowongan disimpan ke {$out} ({$skipped} dilewati: profesi tidak cocok/data tidak lengkap/duplikat).");
        $this->line("Tinjau file, lalu: php artisan skillbridge:import-jobs {$out}");

        return self::SUCCESS;
    }
}
