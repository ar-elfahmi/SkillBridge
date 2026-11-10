<?php

declare(strict_types=1);

namespace App\Modules\Industry\Scraping\Sources;

use App\Modules\Industry\Scraping\JobSource;
use App\Modules\Industry\Scraping\ScrapedJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Jobicy public API (https://jobicy.com/jobs-rss-feed). One request (max 100
 * jobs) per IT-family industry slug; run at most hourly per Jobicy's terms.
 */
final class JobicySource implements JobSource
{
    public function name(): string
    {
        return 'Jobicy';
    }

    public function fetch(): iterable
    {
        /** @var array{endpoint: string, industries: list<string>, geo: ?string, delay_seconds: int} $config */
        $config = config('skillbridge.scraping.sources.jobicy');

        foreach ($config['industries'] as $i => $industry) {
            if ($i > 0) {
                Sleep::for($config['delay_seconds'])->seconds();
            }

            $query = array_filter(
                ['count' => 100, 'industry' => $industry, 'geo' => $config['geo']],
                fn ($v) => $v !== null && $v !== '',
            );

            $jobs = Http::withUserAgent((string) config('skillbridge.scraping.user_agent'))
                ->timeout((int) config('skillbridge.scraping.timeout_seconds'))
                ->acceptJson()
                ->get($config['endpoint'], $query)
                ->throw()
                ->json('jobs', []);

            foreach (is_array($jobs) ? $jobs : [] as $job) {
                if (! is_array($job)) {
                    continue;
                }

                yield new ScrapedJob(
                    company: (string) ($job['companyName'] ?? ''),
                    title: (string) ($job['jobTitle'] ?? ''),
                    description: (string) ($job['jobDescription'] ?? ''),
                    url: (string) ($job['url'] ?? ''),
                );
            }
        }
    }
}
