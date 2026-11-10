<?php

declare(strict_types=1);

namespace App\Modules\Industry\Scraping\Sources;

use App\Modules\Industry\Scraping\JobSource;
use App\Modules\Industry\Scraping\ScrapedJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Himalayas public search API (https://himalayas.app/api). Filtered by country
 * (default Indonesia = jobs open to applicants there) and by IT parentCategories.
 * Pages until an empty page or max_pages.
 */
final class HimalayasSource implements JobSource
{
    public function name(): string
    {
        return 'Himalayas';
    }

    public function fetch(): iterable
    {
        /** @var array{endpoint: string, country: ?string, max_pages: int, delay_seconds: int, it_categories: list<string>} $config */
        $config = config('skillbridge.scraping.sources.himalayas');
        $itCategories = array_map('mb_strtolower', $config['it_categories']);

        for ($page = 1; $page <= $config['max_pages']; $page++) {
            if ($page > 1) {
                Sleep::for($config['delay_seconds'])->seconds();
            }

            $query = array_filter(['country' => $config['country'], 'page' => $page], fn ($v) => $v !== null && $v !== '');

            $jobs = Http::withUserAgent((string) config('skillbridge.scraping.user_agent'))
                ->timeout((int) config('skillbridge.scraping.timeout_seconds'))
                ->acceptJson()
                ->get($config['endpoint'], $query)
                ->throw()
                ->json('jobs', []);

            if (! is_array($jobs) || $jobs === []) {
                break;
            }

            foreach ($jobs as $job) {
                if (! is_array($job)) {
                    continue;
                }

                $parents = array_map(fn ($c) => mb_strtolower((string) $c), (array) ($job['parentCategories'] ?? []));
                if (array_intersect($parents, $itCategories) === []) {
                    continue;
                }

                yield new ScrapedJob(
                    company: (string) ($job['companyName'] ?? ''),
                    title: (string) ($job['title'] ?? ''),
                    description: (string) ($job['description'] ?? ''),
                    url: (string) ($job['applicationLink'] ?? ''),
                );
            }
        }
    }
}
