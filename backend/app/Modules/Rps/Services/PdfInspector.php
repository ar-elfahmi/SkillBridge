<?php

declare(strict_types=1);

namespace App\Modules\Rps\Services;

use App\Modules\Rps\Exceptions\InvalidPdfException;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfInspector
{
    public function pageCount(string $absolutePath): int
    {
        $count = count($this->parse($absolutePath)->getPages());

        if ($count === 0) {
            throw new InvalidPdfException;
        }

        return $count;
    }

    /**
     * Text per page, in page order (index 0 = page 1). Whitespace is normalized
     * so evidence matching can rely on single spaces.
     *
     * @return list<string>
     */
    public function pageTexts(string $absolutePath): array
    {
        $texts = [];

        try {
            foreach ($this->parse($absolutePath)->getPages() as $page) {
                $texts[] = $this->normalize($page->getText());
            }
        } catch (InvalidPdfException $e) {
            throw $e;
        } catch (Throwable) {
            throw new InvalidPdfException;
        }

        if ($texts === []) {
            throw new InvalidPdfException;
        }

        return $texts;
    }

    private function parse(string $absolutePath): Document
    {
        try {
            return (new Parser)->parseFile($absolutePath);
        } catch (Throwable) {
            // Never surface parser internals (or document text) to the user.
            throw new InvalidPdfException;
        }
    }

    private function normalize(string $text): string
    {
        // Strip invalid UTF-8 and NULs (PostgreSQL text rejects \0).
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = str_replace("\0", '', $text);

        // Collapse runs of horizontal whitespace, keep line breaks.
        $text = (string) preg_replace('/[^\S\n]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n */u', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }
}
