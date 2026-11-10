<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Builds small, valid PDFs in memory so tests need no binary fixtures.
 */
final class PdfFixture
{
    /**
     * @param  list<string>  $pageTexts  one entry per page; '' produces a page without a text layer
     */
    public static function make(array $pageTexts): string
    {
        $count = count($pageTexts);
        $objects = [];

        // 1: catalog, 2: pages, 3: font, then (page, content) pairs.
        $kids = [];
        for ($i = 0; $i < $count; $i++) {
            $kids[] = (4 + $i * 2).' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$count.' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        foreach ($pageTexts as $i => $text) {
            $pageId = 4 + $i * 2;
            $contentId = $pageId + 1;
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
            $stream = $text === '' ? '' : "BT /F1 12 Tf 72 720 Td ({$escaped}) Tj ET";

            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
                .'/Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    /**
     * @param  list<string>  $pageTexts
     */
    public static function upload(array $pageTexts, string $name = 'rps.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::make($pageTexts));
    }

    public static function pages(int $count, string $name = 'rps.pdf'): UploadedFile
    {
        $texts = [];
        for ($i = 1; $i <= $count; $i++) {
            $texts[] = "Halaman {$i} RPS Pemrograman Web";
        }

        return self::upload($texts, $name);
    }
}
