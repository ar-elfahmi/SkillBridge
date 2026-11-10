<?php

declare(strict_types=1);

namespace App\Modules\Industry\Scraping;

/**
 * One posting as returned by a source, before career mapping.
 * Description is still raw (may contain HTML).
 */
final readonly class ScrapedJob
{
    public function __construct(
        public string $company,
        public string $title,
        public string $description,
        public string $url,
    ) {}
}
