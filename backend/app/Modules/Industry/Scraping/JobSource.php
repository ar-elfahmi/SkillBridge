<?php

declare(strict_types=1);

namespace App\Modules\Industry\Scraping;

interface JobSource
{
    /** Human-readable name stored as job_postings.source_name (attribution). */
    public function name(): string;

    /**
     * Fetches raw postings. Implementations must respect the source's rate limits.
     *
     * @return iterable<ScrapedJob>
     */
    public function fetch(): iterable;
}
