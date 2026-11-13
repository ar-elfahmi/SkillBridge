<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Models\Career;
use App\Models\JobPosting;
use Database\Seeders\CareerSeeder;
use Database\Seeders\PartsJobPostingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PartsJobPostingSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/parts-seeder-test-'.bin2hex(random_bytes(4));
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir.'/*') ?: []);
        rmdir($this->tempDir);

        parent::tearDown();
    }

    public function test_resolves_parts_directory_from_default_or_env(): void
    {
        putenv("PARTS_DATA_PATH={$this->tempDir}");
        $this->assertSame(realpath($this->tempDir), PartsJobPostingSeeder::resolvePartsDirectory());
        putenv('PARTS_DATA_PATH');
    }

    public function test_seeds_jobs_from_parts_json_and_is_idempotent(): void
    {
        $part1 = [
            'source_file' => 'all-2026-11-10.json',
            'part' => 1,
            'jobs' => [
                [
                    'career' => 'backend-developer',
                    'company' => 'Acme Corp',
                    'title' => 'Senior Backend Engineer',
                    'description' => 'Membangun arsitektur microservices dan API performa tinggi.',
                    'qualifications' => 'Laravel, PostgreSQL, Redis',
                    'source_name' => 'Arbeitnow',
                    'source_url' => 'https://example.com/job/1',
                    'collected_at' => '2026-11-10',
                    'dataset_version' => 'arbeitnow-2026-11-10',
                ],
                [
                    'career' => 'frontend-developer',
                    'company' => 'Beta Tech',
                    'title' => 'Frontend Specialist',
                    'description' => 'Mengembangkan antarmuka responsif dengan React dan Tailwind.',
                    'source_name' => 'Jobicy',
                    'source_url' => 'https://example.com/job/2',
                    'collected_at' => '2026-11-10',
                    'dataset_version' => 'jobicy-2026-11-10',
                ],
            ],
        ];

        $part2 = [
            'source_file' => 'all-2026-11-10.json',
            'part' => 2,
            'jobs' => [
                [
                    'career' => 'data-analyst',
                    'company' => 'Gamma Analytics',
                    'title' => 'Junior Data Analyst',
                    'description' => 'Menganalisis pola data bisnis dan membuat dashboard visualisasi.',
                    'source_name' => 'Himalayas',
                    'source_url' => 'https://example.com/job/3',
                    'collected_at' => '2026-11-10',
                    'dataset_version' => 'himalayas-2026-11-10',
                ],
            ],
        ];

        file_put_contents($this->tempDir.'/all-2026-11-10-part01.json', (string) json_encode($part1));
        file_put_contents($this->tempDir.'/all-2026-11-10-part02.json', (string) json_encode($part2));

        $seeder = app(PartsJobPostingSeeder::class);
        $result = $seeder->run(app(\App\Modules\Industry\Services\JobPostingImporter::class), $this->tempDir);

        $this->assertSame(3, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(2, $result['files']);

        $this->assertDatabaseCount('job_postings', 3);

        $backendJob = JobPosting::where('title', 'Senior Backend Engineer')->sole();
        $this->assertSame('Acme Corp', $backendJob->company);
        $this->assertSame('backend-developer', $backendJob->career->slug);
        $this->assertNull($backendJob->created_by);
        $this->assertSame('pending', $backendJob->extraction_status->value);
        $this->assertSame('arbeitnow-2026-11-10', $backendJob->dataset_version);

        // Idempotency: re-running should update instead of duplicate
        $resultAgain = $seeder->run(app(\App\Modules\Industry\Services\JobPostingImporter::class), $this->tempDir);
        $this->assertSame(0, $resultAgain['created']);
        $this->assertSame(3, $resultAgain['updated']);
        $this->assertDatabaseCount('job_postings', 3);
    }

    public function test_throws_when_folder_does_not_exist(): void
    {
        $seeder = app(PartsJobPostingSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Folder parts tidak ditemukan');

        $seeder->run(app(\App\Modules\Industry\Services\JobPostingImporter::class), $this->tempDir.'/non_existent');
    }

    public function test_throws_when_folder_has_no_json(): void
    {
        $seeder = app(PartsJobPostingSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada file .json ditemukan di folder parts');

        $seeder->run(app(\App\Modules\Industry\Services\JobPostingImporter::class), $this->tempDir);
    }

    public function test_throws_when_row_is_invalid(): void
    {
        $invalidPart = [
            'jobs' => [
                [
                    'career' => 'invalid-career',
                    'company' => 'Bad Co',
                    'title' => 'Bad Title',
                    'description' => 'Desc',
                ],
            ],
        ];

        file_put_contents($this->tempDir.'/bad.json', (string) json_encode($invalidPart));

        $seeder = app(PartsJobPostingSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('berisi 1 baris tidak valid');

        $seeder->run(app(\App\Modules\Industry\Services\JobPostingImporter::class), $this->tempDir);
    }
}
