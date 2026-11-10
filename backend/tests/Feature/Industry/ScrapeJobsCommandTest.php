<?php

declare(strict_types=1);

namespace Tests\Feature\Industry;

use App\Modules\Industry\Scraping\JobScraper;
use Database\Seeders\CareerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ScrapeJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        Http::preventStrayRequests();
        $this->out = sys_get_temp_dir().'/skillbridge-scrape-'.bin2hex(random_bytes(4)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->out)) {
            unlink($this->out);
        }

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function job(string $title, string $url, string $company = 'Acme'): array
    {
        return [
            'company_name' => $company,
            'title' => $title,
            'url' => $url,
            'description' => '<h2>Role</h2><p>Build REST APIs&nbsp;with <b>Laravel</b>.</p><ul><li>PostgreSQL</li><li>Docker</li></ul>',
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function outputRows(): array
    {
        return json_decode((string) file_get_contents($this->out), true)['jobs'];
    }

    public function test_remotive_writes_importable_json_with_attribution(): void
    {
        Http::fake([
            'remotive.com/*' => Http::sequence()
                ->push(['jobs' => [
                    $this->job('Senior Backend Engineer', 'https://remotive.com/remote-jobs/software-dev/a-1'),
                    $this->job('Fullstack Developer (React/Node)', 'https://remotive.com/remote-jobs/software-dev/b-2'),
                    $this->job('Product Designer', 'https://remotive.com/remote-jobs/design/c-3'),
                ]])
                ->push(['jobs' => [
                    $this->job('Data Analyst', 'https://remotive.com/remote-jobs/data/d-4'),
                    $this->job('Senior Backend Engineer', 'https://remotive.com/remote-jobs/software-dev/a-1'),
                ]]),
        ]);

        $this->artisan('skillbridge:scrape-jobs', ['source' => 'remotive', '--out' => $this->out])
            ->assertSuccessful();

        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);

        $rows = $this->outputRows();
        $this->assertSame(
            ['backend-developer', 'full-stack-developer', 'data-analyst'],
            array_column($rows, 'career'),
        );
        $this->assertSame('Remotive', $rows[0]['source_name']);
        $this->assertSame('https://remotive.com/remote-jobs/software-dev/a-1', $rows[0]['source_url']);
        $this->assertSame("Role\nBuild REST APIs with Laravel.\n- PostgreSQL\n- Docker", $rows[0]['description']);
        $this->assertSame('remotive-'.now()->format('Y-m-d'), $rows[0]['dataset_version']);

        // The file feeds straight into the existing importer.
        $this->seed(CareerSeeder::class);
        $this->artisan('skillbridge:import-jobs', ['file' => $this->out])->assertSuccessful();
        $this->assertDatabaseCount('job_postings', 3);
        $this->assertDatabaseHas('job_postings', ['source_name' => 'Remotive', 'created_by' => null]);
    }

    public function test_arbeitnow_paginates_until_limit(): void
    {
        Http::fake([
            'www.arbeitnow.com/*' => Http::sequence()
                ->push(['data' => [
                    $this->job('Frontend Developer', 'https://www.arbeitnow.com/jobs/x-1'),
                    $this->job('Lagerist', 'https://www.arbeitnow.com/jobs/x-2'),
                ], 'links' => ['next' => 'https://www.arbeitnow.com/api/job-board-api?page=2']])
                ->push(['data' => [
                    $this->job('PHP Developer', 'https://www.arbeitnow.com/jobs/x-3'),
                    $this->job('React Engineer', 'https://www.arbeitnow.com/jobs/x-4'),
                ], 'links' => ['next' => 'https://www.arbeitnow.com/api/job-board-api?page=3']]),
        ]);

        $this->artisan('skillbridge:scrape-jobs', ['source' => 'arbeitnow', '--limit' => 2, '--out' => $this->out])
            ->assertSuccessful();

        $rows = $this->outputRows();
        $this->assertSame(['frontend-developer', 'backend-developer'], array_column($rows, 'career'));
        $this->assertSame('Arbeitnow', $rows[0]['source_name']);
        Http::assertSentCount(2);
    }

    public function test_http_error_fails_without_writing_file(): void
    {
        Http::fake(['remotive.com/*' => Http::response('rate limited', 429)]);

        $this->artisan('skillbridge:scrape-jobs', ['source' => 'remotive', '--out' => $this->out])
            ->assertFailed();

        $this->assertFileDoesNotExist($this->out);
    }

    public function test_unknown_source_is_rejected(): void
    {
        $this->artisan('skillbridge:scrape-jobs', ['source' => 'linkedin'])->assertFailed();
    }

    public function test_career_keywords_match_whole_words_only(): void
    {
        $scraper = new JobScraper;

        $this->assertNull($scraper->careerFor('Rapid Prototyper'));       // "api" inside a word
        $this->assertNull($scraper->careerFor('Data Engineer'));
        $this->assertSame('frontend-developer', $scraper->careerFor('Front-End Engineer'));
        $this->assertSame('full-stack-developer', $scraper->careerFor('Full Stack PHP Developer'));
    }
}
