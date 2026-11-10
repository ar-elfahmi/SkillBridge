<?php

declare(strict_types=1);

namespace App\Modules\Industry\Scraping\Sources;

use App\Modules\Industry\Scraping\JobSource;
use App\Modules\Industry\Scraping\ScrapedJob;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Public Remotive API (https://github.com/remotive-io/remote-jobs-api).
 * One request per configured category, spaced to stay under 2 req/min.
 */
final class RemotiveSource implements JobSource
{
    public function name(): string
    {
        return 'Remotive';
    }

    public function fetch(): iterable
    {
        /** @var array{endpoint: string, categories: list<string>, delay_seconds: int} $config */
        $config = config('skillbridge.scraping.sources.remotive');

        foreach ($config['categories'] as $i => $category) {
            if ($i > 0) {
                Sleep::for($config['delay_seconds'])->seconds();
            }

            $jobs = $this->http()
                ->get($config['endpoint'], ['category' => $category])
                ->throw()
                ->json('jobs', []);

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
        }
    }

    private function http(): PendingRequest
    {
        return Http::withUserAgent((string) config('skillbridge.scraping.user_agent'))
            ->timeout((int) config('skillbridge.scraping.timeout_seconds'))
            ->acceptJson();
    }
}
