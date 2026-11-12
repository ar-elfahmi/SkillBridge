<?php

declare(strict_types=1);

namespace App\Modules\Competency\Services;

use App\Enums\VerificationStatus;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use Illuminate\Support\Collection;

class EvidenceVerifier
{
    /**
     * Verify whether an evidence quote exists in an RPS document.
     *
     * Normalizes text (quotes, dashes, sequential whitespace) and checks substring
     * case-insensitively. If the page is specified, it checks that page first; if not
     * found there, searches all pages. If still not found, marks as unverified and nulls page.
     */
    public function verifyDocument(string $evidence, ?int $pageNumber, RpsDocument $document): EvidenceVerificationResult
    {
        $pages = $document->pages()
            ->orderBy('page_number')
            ->get();

        return $this->verifyPages($evidence, $pageNumber, $pages);
    }

    /**
     * Verify evidence against an iterable/collection of RpsPage instances or page text map.
     *
     * @param  Collection<int, RpsPage>|array<int, RpsPage|string>  $pages
     */
    public function verifyPages(string $evidence, ?int $pageNumber, iterable $pages): EvidenceVerificationResult
    {
        $normEvidence = $this->normalizeText($evidence);
        if ($normEvidence === '') {
            return new EvidenceVerificationResult(VerificationStatus::Unverified, null, '');
        }

        // Convert iterable to page_number => normalized_text map
        $pageMap = $this->buildPageMap($pages);
        if (empty($pageMap)) {
            return new EvidenceVerificationResult(VerificationStatus::Unverified, null, $normEvidence);
        }

        $needle = mb_strtolower($normEvidence);

        // 1. If pageNumber is given and exists in the map, check it first
        if ($pageNumber !== null && isset($pageMap[$pageNumber])) {
            if (str_contains(mb_strtolower($pageMap[$pageNumber]), $needle)) {
                return new EvidenceVerificationResult(VerificationStatus::Verified, $pageNumber, $normEvidence);
            }
        }

        // 2. Search across all pages in order
        foreach ($pageMap as $pNum => $pText) {
            if (str_contains(mb_strtolower($pText), $needle)) {
                return new EvidenceVerificationResult(VerificationStatus::Verified, $pNum, $normEvidence);
            }
        }

        // 3. Not found anywhere -> unverified, page untrusted (null)
        return new EvidenceVerificationResult(VerificationStatus::Unverified, null, $normEvidence);
    }

    /**
     * Check if normalized evidence is a substring of normalized source text.
     */
    public function verifyInText(string $evidence, string $sourceText): bool
    {
        $normEvidence = $this->normalizeText($evidence);
        if ($normEvidence === '') {
            return false;
        }

        $normSource = $this->normalizeText($sourceText);

        return str_contains(mb_strtolower($normSource), mb_strtolower($normEvidence));
    }

    /**
     * Normalize quotes, dashes, hyphens, sequential whitespace, and trim.
     */
    public function normalizeText(string $text): string
    {
        // 1. Normalize smart quotes to standard quotes
        $text = str_replace(["\xE2\x80\x9C", "\xE2\x80\x9D", '“', '”', '«', '»'], '"', $text);
        $text = str_replace(["\xE2\x80\x98", "\xE2\x80\x99", '‘', '’', '`'], "'", $text);

        // 2. Normalize hyphens and dashes (en-dash, em-dash, minus, soft hyphen)
        $text = str_replace(["\xE2\x80\x93", "\xE2\x80\x94", "\xE2\x88\x92", '–', '—', '−', "\xC2\xAD"], '-', $text);

        // 3. Collapse whitespace and trim
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * @param  Collection<int, RpsPage>|array<int, RpsPage|string>  $pages
     * @return array<int, string> [page_number => normalized_text]
     */
    private function buildPageMap(iterable $pages): array
    {
        $map = [];

        foreach ($pages as $key => $item) {
            if ($item instanceof RpsPage) {
                $map[$item->page_number] = $this->normalizeText($item->text);
            } elseif (is_string($item)) {
                $pNum = is_int($key) ? $key : (int) $key;
                $map[$pNum] = $this->normalizeText($item);
            }
        }

        ksort($map);

        return $map;
    }
}
