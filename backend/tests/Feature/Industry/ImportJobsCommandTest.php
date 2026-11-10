<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Models\JobPosting;
use Database\Seeders\CareerSeeder;
use Database\Seeders\SampleJobPostingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CareerSeeder::class);
        $this->dir = sys_get_temp_dir().'/skillbridge-import-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);

        parent::tearDown();
    }

    private function file(string $name, string $content): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'career' => 'backend-developer',
            'company' => 'PT Uji',
            'title' => 'Backend Developer',
            'description' => 'Membangun REST API dengan Laravel.',
            'qualifications' => 'Git, Docker',
            'source_url' => 'https://example.com/jobs/1',
            'collected_at' => '2026-11-01',
            'dataset_version' => 'v1',
        ], $overrides);
    }

    public function test_imports_json_as_curated_pending_postings(): void
    {
        $path = $this->file('jobs.json', (string) json_encode([$this->row(), $this->row(['career' => 'data-analyst', 'title' => 'Data Analyst'])]));

        $this->artisan('skillbridge:import-jobs', ['file' => $path])
            ->expectsOutputToContain('Selesai: 2 lowongan baru, 0 diperbarui.')
            ->assertSuccessful();

        $posting = JobPosting::where('title', 'Backend Developer')->sole();
        $this->assertTrue($posting->isCurated());
        $this->assertSame('backend-developer', $posting->career->slug);
        $this->assertSame('pending', $posting->extraction_status->value);
        $this->assertSame('2026-11-01', $posting->collected_at?->toDateString());
        $this->assertSame('https://example.com/jobs/1', $posting->source_url);
    }

    public function test_accepts_jobs_wrapper_object(): void
    {
        $path = $this->file('jobs.json', (string) json_encode(['jobs' => [$this->row()]]));

        $this->artisan('skillbridge:import-jobs', ['file' => $path])->assertSuccessful();

        $this->assertDatabaseCount('job_postings', 1);
    }

    public function test_imports_csv_with_bom_quotes_and_empty_optionals(): void
    {
        $csv = "\xEF\xBB\xBFcareer,company,title,description,qualifications,source_url,collected_at,dataset_version\n"
            ."frontend-developer,PT Uji,Frontend Developer,\"React, TypeScript, dan \"\"Tailwind\"\"\",,,,v1\n"
            ."\n";

        $this->artisan('skillbridge:import-jobs', ['file' => $this->file('jobs.csv', $csv)])->assertSuccessful();

        $posting = JobPosting::sole();
        $this->assertSame('React, TypeScript, dan "Tailwind"', $posting->description);
        $this->assertNull($posting->qualifications);
        $this->assertNull($posting->source_url);
        $this->assertNull($posting->collected_at);
    }

    public function test_reimport_updates_instead_of_duplicating(): void
    {
        $path = $this->file('jobs.json', (string) json_encode([$this->row()]));
        $this->artisan('skillbridge:import-jobs', ['file' => $path])->assertSuccessful();

        file_put_contents($path, (string) json_encode([$this->row(['description' => 'Deskripsi baru.'])]));
        $this->artisan('skillbridge:import-jobs', ['file' => $path])
            ->expectsOutputToContain('0 lowongan baru, 1 diperbarui')
            ->assertSuccessful();

        $this->assertSame('Deskripsi baru.', JobPosting::sole()->description);
    }

    public function test_invalid_row_aborts_whole_import_with_row_numbers(): void
    {
        $path = $this->file('jobs.json', (string) json_encode([
            $this->row(),
            $this->row(['career' => 'astronaut', 'source_url' => 'javascript:alert(1)', 'collected_at' => '01/11/2026']),
            $this->row(['description' => '']),
        ]));

        $this->artisan('skillbridge:import-jobs', ['file' => $path])
            ->expectsOutputToContain('Baris 2:')
            ->expectsOutputToContain('Baris 3:')
            ->expectsOutputToContain('2 baris tidak valid. Tidak ada yang diimpor.')
            ->assertFailed();

        $this->assertDatabaseCount('job_postings', 0);
    }

    public function test_description_length_is_limited(): void
    {
        $path = $this->file('jobs.json', (string) json_encode([$this->row(['description' => str_repeat('a', 20_001)])]));

        $this->artisan('skillbridge:import-jobs', ['file' => $path])->assertFailed();

        $this->assertDatabaseCount('job_postings', 0);
    }

    public function test_dry_run_validates_without_saving(): void
    {
        $path = $this->file('jobs.json', (string) json_encode([$this->row()]));

        $this->artisan('skillbridge:import-jobs', ['file' => $path, '--dry-run' => true])
            ->expectsOutputToContain('1 baris valid (dry run')
            ->assertSuccessful();

        $this->assertDatabaseCount('job_postings', 0);
    }

    public function test_dataset_version_option_fills_missing_values_only(): void
    {
        $path = $this->file('jobs.json', (string) json_encode([
            $this->row(['dataset_version' => null, 'title' => 'A']),
            $this->row(['dataset_version' => 'keep', 'title' => 'B']),
        ]));

        $this->artisan('skillbridge:import-jobs', ['file' => $path, '--dataset-version' => '2026-11'])->assertSuccessful();

        $this->assertSame('2026-11', JobPosting::where('title', 'A')->value('dataset_version'));
        $this->assertSame('keep', JobPosting::where('title', 'B')->value('dataset_version'));
    }

    public function test_rejects_missing_file_bad_json_and_unsupported_format(): void
    {
        $this->artisan('skillbridge:import-jobs', ['file' => $this->dir.'/nope.json'])->assertFailed();
        $this->artisan('skillbridge:import-jobs', ['file' => $this->file('bad.json', '{not json')])
            ->expectsOutputToContain('JSON tidak valid.')
            ->assertFailed();
        $this->artisan('skillbridge:import-jobs', ['file' => $this->file('jobs.xml', '<jobs/>')])
            ->expectsOutputToContain('Format tidak didukung')
            ->assertFailed();
    }

    public function test_sample_seeder_labels_data_contoh_and_is_idempotent(): void
    {
        $this->seed(SampleJobPostingSeeder::class);
        $count = JobPosting::count();
        $this->seed(SampleJobPostingSeeder::class);

        $this->assertSame(8, $count);
        $this->assertSame($count, JobPosting::count());
        $this->assertSame(0, JobPosting::where('source_name', '!=', 'data contoh')->count());
        $this->assertSame(0, JobPosting::whereNotNull('source_url')->count());
        $this->assertSame(0, JobPosting::whereNotNull('created_by')->count());
        $this->assertSame([SampleJobPostingSeeder::DATASET_VERSION], JobPosting::distinct()->pluck('dataset_version')->all());

        // Every MVP career has sample postings.
        foreach (array_keys(CareerSeeder::careers()) as $slug) {
            $this->assertGreaterThan(0, JobPosting::whereHas('career', fn ($q) => $q->where('slug', $slug))->count(), $slug);
        }
    }
}
