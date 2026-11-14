<?php

declare(strict_types=1);

namespace App\Modules\Industry\Services;

use App\Models\Career;
use App\Models\JobPosting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use JsonException;

/**
 * Imports curated job postings (created_by = null) from CSV or JSON.
 *
 * Row fields: career (slug), company, title, description, qualifications?,
 * source_name?, source_url?, collected_at? (Y-m-d), dataset_version?.
 * All-or-nothing: any invalid row aborts the import. Re-importing the same
 * row (career + company + title + source_url + dataset_version) updates it
 * instead of creating a duplicate.
 */
class JobPostingImporter
{
    public const MAX_TEXT_LENGTH = 20_000;

    /**
     * @return list<array<string, mixed>>
     */
    public function readFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("File tidak ditemukan atau tidak bisa dibaca: {$path}");
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'json' => $this->readJson($path),
            'csv' => $this->readCsv($path),
            default => throw new InvalidArgumentException('Format tidak didukung. Gunakan .csv atau .json.'),
        };
    }

    /**
     * Validates every row first; returns errors keyed by 1-based row number.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, list<string>>
     */
    public function validate(array $rows): array
    {
        $careerSlugs = Career::query()->pluck('slug')->all();
        $errors = [];

        foreach ($rows as $i => $row) {
            $validator = Validator::make($row, [
                'career' => ['required', 'string', 'in:'.implode(',', $careerSlugs)],
                'company' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'max:'.self::MAX_TEXT_LENGTH],
                'qualifications' => ['nullable', 'string', 'max:'.self::MAX_TEXT_LENGTH],
                'source_name' => ['nullable', 'string', 'max:255'],
                'source_url' => ['nullable', 'url:http,https', 'max:255'],
                'collected_at' => ['nullable', 'date_format:Y-m-d'],
                'dataset_version' => ['nullable', 'string', 'max:255'],
            ], [
                'career.in' => 'career harus slug profesi yang ada ('.implode(', ', $careerSlugs).').',
            ]);

            if ($validator->fails()) {
                $errors[$i + 1] = $validator->errors()->all();
            }
        }

        return $errors;
    }

    /**
     * @param  list<array<string, mixed>>  $rows  rows that passed validate()
     * @return array{created: int, updated: int}
     */
    public function import(array $rows): array
    {
        $careerIds = Career::query()->pluck('id', 'slug');
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $careerIds, &$created, &$updated): void {
            foreach ($rows as $row) {
                $description = $row['description'];
                $title = $this->stripLocaleSuffix($row['title']);

                $posting = JobPosting::query()->updateOrCreate(
                    [
                        'created_by' => null,
                        'career_id' => $careerIds[$row['career']],
                        'company' => $row['company'],
                        'title' => $title,
                        'source_url' => $row['source_url'] ?? null,
                        'dataset_version' => $row['dataset_version'] ?? null,
                    ],
                    [
                        'description' => $description,
                        'qualifications' => $row['qualifications'] ?? $this->deriveQualifications($description),
                        'source_name' => $row['source_name'] ?? null,
                        'collected_at' => $row['collected_at'] ?? null,
                    ],
                );

                $posting->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Removes locale/gender suffixes commonly appended to scraped titles,
     * e.g. "Software Engineer (m/f/d)" -> "Software Engineer".
     */
    private function stripLocaleSuffix(string $title): string
    {
        return (string) preg_replace(
            '/\s*\((?:m\/f\/d|m\/w\/d|f\/m\/d|w\/m\/d|x\/f\/m|d\/f\/m|all genders|any gender)\)\s*$/i',
            '',
            trim($title),
        );
    }

    /**
     * Derives qualifications from the description when the source has no
     * dedicated column: explicit section first, then the first bullet list,
     * then qualification-signal sentences.
     */
    private function deriveQualifications(string $description): ?string
    {
        // 0) Indonesian synthetic-dataset header listing common skills.
        if (preg_match(
            '/Skill yang umum dibutuhkan(?: untuk profesi ini)?\s*:\s*(?:\r\n|\r|\n)((?:[ \t]*(?:[\x{2022}\-\*]|\d+[.)])[ \t]*[^\r\n]+(?:\r\n|\r|\n|$))+)/u',
            $description,
            $m,
        )) {
            $list = trim($m[1]);

            if ($list !== '') {
                return mb_substr($list, 0, self::MAX_TEXT_LENGTH);
            }
        }
        // 1) Explicit requirements/qualifications section.
        if (preg_match(
            '/(?:requirements|qualifications|what you.{0,3}ll need|your profile|was wir suchen|we are looking for)[\s:]{1,3}(?:\r\n|\r|\n){1,2}(.{40,2000}?)(?:(?:\r\n|\r|\n)\s*(?:benefits|what we offer|nice to have|we offer|why join)|$)/is',
            $description,
            $m,
        )) {
            $section = trim($m[1]);

            if ($section !== '') {
                return mb_substr($section, 0, self::MAX_TEXT_LENGTH);
            }
        }

        // 2) First bullet/numbered list (common in ATS postings).
        if (preg_match(
            '/((?:[ \t]*(?:\d+[.)]|[\x{2022}\-\*])[ \t]+\S.+(?:\r\n|\r|\n|$)){3,})/u',
            $description,
            $m,
        )) {
            $list = trim($m[1]);

            if (mb_strlen($list) >= 40) {
                return mb_substr($list, 0, self::MAX_TEXT_LENGTH);
            }
        }

        // 3) Sentences carrying qualification signals.
        preg_match_all(
            '/^[^\r\n]{20,220}?\b(?:\d+\+?\s*(?:years?|jahre)|experience (?:with|in|of)|proficien\w*|familiar with|bachelor|master.s degree|fresh graduate|pengalaman)[^\r\n]{0,180}[.!?]/im',
            $description,
            $m,
        );
        $lines = array_slice(array_values(array_unique(array_map('trim', $m[0] ?? []))), 0, 5);

        if ($lines === []) {
            return null;
        }

        return mb_substr(implode("\n", $lines), 0, self::MAX_TEXT_LENGTH);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readJson(string $path): array
    {
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('JSON tidak valid.');
        }

        // Accept either a top-level array or { "jobs": [...] }.
        if (is_array($data) && array_key_exists('jobs', $data)) {
            $data = $data['jobs'];
        }

        if (! is_array($data) || ! array_is_list($data)) {
            throw new InvalidArgumentException('JSON harus berupa array lowongan atau { "jobs": [...] }.');
        }

        return array_map(fn (mixed $row) => $this->clean(is_array($row) ? $row : []), $data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException("File tidak bisa dibuka: {$path}");
        }

        $header = fgetcsv($handle, escape: '');
        if ($header === false) {
            fclose($handle);

            return [];
        }

        // Strip UTF-8 BOM that spreadsheet exports add.
        $header = array_map(fn ($h) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $header);
        $rows = [];

        while (($line = fgetcsv($handle, escape: '')) !== false) {
            if ($line === [null]) {
                continue; // blank line
            }

            $rows[] = $this->clean(array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), null)));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Trim strings; empty strings become null so optional fields stay optional.
     *
     * @param  array<array-key, mixed>  $row
     * @return array<string, mixed>
     */
    private function clean(array $row): array
    {
        $clean = [];

        foreach ($row as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;
            $clean[(string) $key] = $value === '' ? null : $value;
        }

        return $clean;
    }
}
