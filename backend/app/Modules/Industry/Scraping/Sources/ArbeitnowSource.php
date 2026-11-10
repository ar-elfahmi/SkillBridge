<?php

declare(strict_types=1);

namespace App\Modules\Industry\Scraping\Sources;

use App\Modules\Industry\Scraping\JobSource;
use App\Modules\Industry\Scraping\ScrapedJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Free Arbeitnow job board API (https://www.arbeitnow.com/blog/job-board-api).
 * Paginated (`?page=N`); stops at max_pages or when `links.next` is empty.
 */
final class ArbeitnowSource implements JobSource
{
    public function name(): string
    {
        return 'Arbeitnow';
    }

    public function fetch(): iterable
    {
        /** @var array{endpoint: string, max_pages: int, delay_seconds: int} $config */
        $config = config('skillbridge.scraping.sources.arbeitnow');

        for ($page = 1; $page <= $config['max_pages']; $page++) {
            if ($page > 1) {
                Sleep::for($config['delay_seconds'])->seconds();
            }

            $response = Http::withUserAgent((string) config('skillbridge.scraping.user_agent'))
                ->timeout((int) config('skillbridge.scraping.timeout_seconds'))
                ->acceptJson()
                ->get($config['endpoint'], ['page' => $page])
                ->throw();

            $jobs = $response->json('data', []);

            foreach (is_array($jobs) ? $jobs : [] as $job) {
                if (! is_array($job)) {
                    continue;
                }

                yield new ScrapedJob(
                    company: (string) ($job['company_name'] ?? ''),
                    title: (string) ($job['title'] ?? ''),
                    description: (string) ($job['description'] ?? ''),
                    url: (string) ($job['url'] ?? ''),
                );
            }

            if (empty($response->json('links.next'))) {
                break;
            }
        }
    }
}
