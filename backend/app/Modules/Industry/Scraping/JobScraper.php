<?php

declare(strict_types=1);

namespace App\Modules\Industry\Scraping;

use App\Modules\Industry\Services\JobPostingImporter;

/**
 * Turns raw postings from a source into rows accepted by JobPostingImporter.
 * Postings whose title maps to no career, or with missing/oversized fields,
 * are skipped (counted, never guessed).
 */
final class JobScraper
{
    /**
     * @return array{rows: list<array<string, string>>, skipped: int}
     */
    public function collect(JobSource $source, int $limit, string $datasetVersion, string $collectedAt): array
    {
        $rows = [];
        $seenUrls = [];
        $skipped = 0;

        foreach ($source->fetch() as $job) {
            if (count($rows) >= $limit) {
                break;
            }

            $career = $this->careerFor($job->title);
            $description = $this->toText($job->description);
            $company = trim($job->company);
            $title = trim($job->title);
            $url = trim($job->url);

            if ($career === null || $company === '' || $title === '' || $description === ''
                || ! filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > 255
                || mb_strlen($company) > 255 || mb_strlen($title) > 255
                || isset($seenUrls[$url])) {
                $skipped++;

                continue;
            }

            $seenUrls[$url] = true;
            $rows[] = [
                'career' => $career,
                'company' => $company,
                'title' => $title,
                'description' => mb_substr($description, 0, JobPostingImporter::MAX_TEXT_LENGTH),
                'source_name' => $source->name(),
                'source_url' => $url,
                'collected_at' => $collectedAt,
                'dataset_version' => $datasetVersion,
            ];
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /** Career slug for an IT-family title, or null (non-IT or no matching career). */
    public function careerFor(string $title): ?string
    {
        /** @var list<string> $excluded */
        $excluded = config('skillbridge.scraping.exclude_title_keywords', []);
        if ($this->matchesAny($title, $excluded)) {
            return null;
        }

        /** @var array<string, list<string>> $map */
        $map = config('skillbridge.scraping.career_keywords', []);

        foreach ($map as $slug => $keywords) {
            if ($this->matchesAny($title, $keywords)) {
                return $slug;
            }
        }

        return null;
    }

    /** @param  list<string>  $keywords  matched as whole words, case-insensitive */
    private function matchesAny(string $title, array $keywords): bool
    {
        $title = mb_strtolower($title);

        foreach ($keywords as $keyword) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($keyword), '/').'(?![\p{L}\p{N}])/u', $title) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Converts HTML descriptions to plain text, keeping paragraph/list breaks. */
    public function toText(string $html): string
    {
        $html = (string) preg_replace('/<\s*(br|\/p|\/li|\/h[1-6]|\/div)\s*\/?>/i', "\n", $html);
        $html = (string) preg_replace('/<\s*li[^>]*>/i', '- ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n[ \n]*/', "\n", $text);

        return trim($text);
    }
}
