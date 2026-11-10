<?php

declare(strict_types=1);

namespace App\Modules\Rps\Services;

use App\Modules\Rps\Exceptions\InvalidPdfException;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfInspector
{
    public function pageCount(string $absolutePath): int
    {
        try {
            $pages = (new Parser)->parseFile($absolutePath)->getPages();
        } catch (Throwable) {
            // Never surface parser internals (or document text) to the user.
            throw new InvalidPdfException;
        }

        $count = count($pages);

        if ($count === 0) {
            throw new InvalidPdfException;
        }

        return $count;
    }
}
