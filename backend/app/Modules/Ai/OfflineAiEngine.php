<?php

declare(strict_types=1);

namespace App\Modules\Ai;

use App\Enums\SkillCategory;
use App\Models\Skill;
use App\Support\Cosine;
use Database\Seeders\SkillSeeder;
use Throwable;

/**
 * Lightweight, zero-dependency local AI engine for full offline or auto-fallback execution.
 * Produces responses strictly adhering to the Gemini envelope and JSON schemas used by SkillBridge.
 */
final class OfflineAiEngine
{
    /**
     * Generate a Gemini-compatible response envelope locally without external HTTP calls.
     *
     * @return array<string, mixed>
     */
    public function generateEnvelope(string $purpose, string $prompt): array
    {
        $documentText = $this->extractDocumentBlock($prompt);

        $resultData = match ($purpose) {
            'rps_metadata' => $this->extractRpsMetadata($documentText),
            'rps_extraction' => $this->extractRpsCompetencies($documentText),
            'job_extraction' => $this->extractJobSkills($documentText),
            'match_judge' => $this->judgeMatch($prompt),
            'recommendation' => $this->generateRecommendation($documentText),
            default => [],
        };

        $jsonText = (string) json_encode($resultData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $jsonText],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => (int) ceil(strlen($prompt) / 4),
                'candidatesTokenCount' => (int) ceil(strlen($jsonText) / 4),
                'totalTokenCount' => (int) ceil((strlen($prompt) + strlen($jsonText)) / 4),
            ],
        ];
    }

    /**
     * Compute semantic feature-hashed L2-normalized vectors for a batch of texts.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts, int $dimensions = 768): array
    {
        return array_map(fn (string $text): array => $this->embedText($text, $dimensions), $texts);
    }

    /**
     * Compute a semantic feature-hashed L2-normalized vector for a single text.
     * Uses canonical skill synonym expansion + word unigrams/bigrams + character trigrams
     * so semantically similar Indonesian/English competency descriptions yield high cosine similarity.
     *
     * @return list<float>
     */
    public function embedText(string $text, int $dimensions = 768): array
    {
        $dim = max(1, $dimensions);
        $vector = array_fill(0, $dim, 0.0);

        $normalized = mb_strtolower($text);
        $normalized = (string) preg_replace('/[^\p{L}\p{N}\s.+#\/-]/u', ' ', $normalized);
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $normalized));

        if ($normalized === '') {
            $vector[0] = 1.0;

            return $vector;
        }

        $features = [];
        $catalog = $this->skillCatalog();

        // Detect if text starts with "{skill_name}: {description}" (standard EmbeddingService format)
        $headCanonical = null;
        if (str_contains($text, ':')) {
            [$rawHead] = explode(':', $text, 2);
            $headLower = mb_strtolower(trim($rawHead));
            if ($headLower !== '') {
                foreach ($catalog as $entry) {
                    if (mb_strtolower($entry['canonical']) === $headLower || in_array($headLower, $entry['terms'], true)) {
                        $headCanonical = mb_strtolower($entry['canonical']);
                        break;
                    }
                }
                if ($headCanonical === null && mb_strlen($headLower) <= 45 && ! str_contains($headLower, 'cpmk')) {
                    $headCanonical = $headLower;
                }
            }
        }

        if ($headCanonical !== null) {
            $features['skill_concept:'.$headCanonical] = 24.0;
        }

        // 1. Canonical skill concept expansion
        foreach ($catalog as $entry) {
            $canonicalLower = mb_strtolower($entry['canonical']);
            $canonicalKey = 'skill_concept:'.$canonicalLower;
            foreach ($entry['terms'] as $term) {
                if ($this->containsTerm($normalized, $term)) {
                    $weight = ($headCanonical === null)
                        ? 12.0
                        : ($canonicalLower === $headCanonical ? 8.0 : 2.5);
                    $features[$canonicalKey] = ($features[$canonicalKey] ?? 0.0) + $weight;
                    break;
                }
            }
        }

        // 2. Word unigrams and bigrams (weight = 1.5 and 1.0)
        $stopWords = [
            'dan', 'atau', 'yang', 'di', 'ke', 'dari', 'untuk', 'pada', 'dengan', 'dalam',
            'adalah', 'ini', 'itu', 'serta', 'mampu', 'mahasiswa', 'dapat', 'secara',
            'the', 'and', 'or', 'in', 'on', 'to', 'for', 'with', 'of', 'a', 'an', 'is', 'are',
        ];
        $rawWords = preg_split('/\s+/u', $normalized) ?: [];
        $words = array_values(array_filter(
            $rawWords,
            fn (string $w): bool => mb_strlen($w) >= 2 && ! in_array($w, $stopWords, true)
        ));

        foreach ($words as $i => $word) {
            $features["w:{$word}"] = ($features["w:{$word}"] ?? 0.0) + 1.5;
            if (isset($words[$i + 1])) {
                $bigram = $word.'_'.$words[$i + 1];
                $features["b:{$bigram}"] = ($features["b:{$bigram}"] ?? 0.0) + 1.0;
            }
            // Character 3-grams for morphological robustness
            $len = mb_strlen($word);
            if ($len >= 4) {
                for ($c = 0; $c <= $len - 3; $c++) {
                    $trigram = mb_substr($word, $c, 3);
                    $features["c:{$trigram}"] = ($features["c:{$trigram}"] ?? 0.0) + 0.35;
                }
            }
        }

        foreach ($features as $feature => $weight) {
            // Project each feature into 2 buckets with deterministic sign (signed random projection)
            for ($proj = 0; $proj < 2; $proj++) {
                $hash = hash('sha256', "{$feature}#{$proj}");
                $bucket = (int) (hexdec(substr($hash, 0, 8)) % $dim);
                $sign = (hexdec(substr($hash, 8, 2)) % 2 === 0) ? 1.0 : -1.0;
                $vector[$bucket] += $sign * $weight;
            }
        }

        return Cosine::l2Normalize($vector);
    }

    private function extractDocumentBlock(string $prompt): string
    {
        foreach (['document', 'job', 'data'] as $tag) {
            $extracted = $this->extractLastTagBlock($prompt, $tag);
            if ($extracted !== null) {
                return $extracted;
            }
        }

        return trim($prompt);
    }

    private function extractLastTagBlock(string $prompt, string $tag): ?string
    {
        if (preg_match('/.*<'.$tag.'>(.*?)<\/'.$tag.'>/is', $prompt, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * @return array{name: ?string, code: ?string, credits: ?int, semester: ?int, study_program: ?string}
     */
    private function extractRpsMetadata(string $text): array
    {
        $name = null;
        if (preg_match('/(?:Mata\s+Kuliah|Course\s+Name|Nama\s+MK)\s*[:\-]\s*([^\n\r(|]+)/iu', $text, $m)) {
            $name = trim($m[1], " \t\n\r\0\x0B:.-…");
        }
        if ($name === null && preg_match('/Mata\s+kuliah\s+([A-Z][a-zA-Z\s]{4,60}?)\s+(?:membahas|merupakan|adalah|mempelajari)\b/u', $text, $m)) {
            $name = trim($m[1]);
        }
        if ($name === null && preg_match('/(?:Mata\s+Kuliah(?:\s*\(MK\))?|Course\s+Name|Nama\s+MK)\s*[:\-]?\s*[\r\n]+([^:\r\n(|]+)/iu', $text, $m)) {
            $raw = trim((string) preg_replace('/\s+/', ' ', $m[1]), " \t\n\r\0\x0B:.-…");
            if (mb_strlen($raw) >= 3 && ! preg_match('/^(?:Kode|Bobot|SKS|Semester|Rumpun|Dosen)/iu', $raw)) {
                $name = $raw;
            }
        }

        $code = null;
        if (preg_match('/(?:Kode\s+(?:Mata\s+Kuliah|MK)|Course\s+Code)\s*[:\-]?\s*\b([A-Z]{2,5}[-\s]?\d{3,4}[A-Z]?)\b/iu', $text, $m)) {
            $code = strtoupper((string) preg_replace('/\s+/', '', $m[1]));
        } elseif (preg_match('/\b([A-Z]{2,4}\d{3,4}[A-Z]?)\b/', $text, $m)) {
            $code = $m[1];
        }

        $credits = null;
        if (preg_match('/(?:Bobot|SKS|Kredit)\s*[:\-]?\s*(\d{1,2})\s*(?:SKS)?/iu', $text, $m)) {
            $credits = (int) $m[1];
        } elseif (preg_match('/\b(\d{1,2})\s*SKS\b/iu', $text, $m)) {
            $credits = (int) $m[1];
        }

        $semester = null;
        if (preg_match('/Semester\s*[:\-]?\s*(\d{1,2})/iu', $text, $m)) {
            $semester = (int) $m[1];
        }

        $studyProgram = null;
        if (preg_match('/(?:Program\s+Studi|Prodi|Jurusan)\s*[:\-]\s*([^\n\r.|]+)/iu', $text, $m)) {
            $studyProgram = trim($m[1], " \t\n\r\0\x0B:.-");
        }

        return [
            'name' => $name !== '' ? $name : null,
            'code' => $code !== '' ? $code : null,
            'credits' => $credits,
            'semester' => $semester,
            'study_program' => $studyProgram !== '' ? $studyProgram : null,
        ];
    }

    /**
     * @return array{course: string, competencies: list<array<string, mixed>>}
     */
    private function extractRpsCompetencies(string $text): array
    {
        $meta = $this->extractRpsMetadata($text);
        $courseName = $meta['name'] ?? 'Mata Kuliah RPS';

        $pages = $this->splitPages($text);
        $catalog = $this->skillCatalog();
        $competencies = [];
        $seenKeys = [];

        foreach ($pages as $pageNumber => $pageText) {
            $segments = $this->splitIntoEvidenceSegments($pageText);

            foreach ($segments as $segment) {
                foreach ($catalog as $entry) {
                    foreach ($entry['terms'] as $term) {
                        if ($this->containsTerm(mb_strtolower($segment), $term)) {
                            $dedupKey = mb_strtolower($entry['canonical']);
                            if (isset($seenKeys[$dedupKey])) {
                                break;
                            }
                            $seenKeys[$dedupKey] = true;

                            $sourceType = $this->detectSourceType($segment);
                            $sourceRef = $this->detectSourceReference($segment, $sourceType);
                            $week = $this->detectWeek($segment);

                            $competencies[] = [
                                'skill' => $entry['canonical'],
                                'category' => $entry['category'] ?? 'other',
                                'competency' => $segment,
                                'evidence' => $segment,
                                'source_type' => $sourceType,
                                'source_reference' => $sourceRef,
                                'week' => $week,
                                'page' => $pageNumber,
                            ];
                            break;
                        }
                    }
                }
            }
        }

        return [
            'course' => $courseName,
            'competencies' => $competencies,
        ];
    }

    /**
     * @return array{skills: list<array<string, mixed>>}
     */
    private function extractJobSkills(string $text): array
    {
        $segments = $this->splitIntoEvidenceSegments($text);
        $catalog = $this->skillCatalog();
        $skills = [];
        $seen = [];

        foreach ($segments as $segment) {
            foreach ($catalog as $entry) {
                foreach ($entry['terms'] as $term) {
                    if ($this->containsTerm(mb_strtolower($segment), $term)) {
                        $key = mb_strtolower($entry['canonical']);
                        if (isset($seen[$key])) {
                            break;
                        }
                        $seen[$key] = true;

                        $skills[] = [
                            'skill' => $entry['canonical'],
                            'category' => $entry['category'] ?? 'other',
                            'requirement_text' => $segment,
                            'evidence' => $segment,
                        ];
                        break;
                    }
                }
            }
        }

        return ['skills' => $skills];
    }

    /**
     * @return array<string, mixed>
     */
    private function judgeMatch(string $prompt): array
    {
        $skillName = '';
        $candidates = [];

        $reqText = $this->extractLastTagBlock($prompt, 'requirement') ?? '';
        if ($reqText !== '') {
            $parts = explode(':', $reqText, 2);
            $skillName = trim($parts[0]);
        }

        $candText = $this->extractLastTagBlock($prompt, 'candidates');
        if ($candText !== null && $candText !== '') {
            $decoded = json_decode($candText, true);
            if (is_array($decoded)) {
                $candidates = $decoded;
            }
        }

        if ($candidates === []) {
            return [
                'classification' => 'missing',
                'matched_course_skill_id' => null,
                'reason' => 'Tidak ada kandidat kompetensi RPS yang tersedia.',
                'evidence' => null,
            ];
        }

        $catalogTerms = $skillName !== '' ? [mb_strtolower($skillName)] : [];
        foreach ($this->skillCatalog() as $entry) {
            if (mb_strtolower($entry['canonical']) === mb_strtolower($skillName)) {
                $catalogTerms = $entry['terms'];
                break;
            }
        }

        foreach ($candidates as $cand) {
            if (! is_array($cand)) {
                continue;
            }
            $candId = isset($cand['id']) ? (int) $cand['id'] : (isset($cand['course_skill_id']) ? (int) $cand['course_skill_id'] : null);
            $candComp = (string) ($cand['competency'] ?? '');
            $candEv = isset($cand['evidence']) && is_string($cand['evidence']) && trim($cand['evidence']) !== ''
                ? trim($cand['evidence'])
                : $candComp;
            $combinedLower = mb_strtolower($candComp.' '.$candEv);

            foreach ($catalogTerms as $term) {
                if ($this->containsTerm($combinedLower, $term)) {
                    return [
                        'classification' => 'covered',
                        'matched_course_skill_id' => $candId,
                        'reason' => "Kompetensi RPS mencakup {$skillName} secara langsung pada materi perkuliahan.",
                        'evidence' => $candEv,
                    ];
                }
            }
        }

        $first = (array) $candidates[0];
        $firstId = isset($first['id']) ? (int) $first['id'] : (isset($first['course_skill_id']) ? (int) $first['course_skill_id'] : null);
        $firstComp = (string) ($first['competency'] ?? '');
        $firstEv = isset($first['evidence']) && is_string($first['evidence']) && trim($first['evidence']) !== ''
            ? trim($first['evidence'])
            : $firstComp;

        $reqFull = $reqText !== '' ? $reqText : $skillName;
        $semanticOverlap = Cosine::similarity(
            $this->embedText($reqFull, 256),
            $this->embedText($firstComp.' '.$firstEv, 256)
        );

        if ($semanticOverlap >= 0.65) {
            return [
                'classification' => 'partial',
                'matched_course_skill_id' => $firstId,
                'reason' => "RPS mencakup konsep terkait namun cakupan spesifik {$skillName} masih bersifat sebagian.",
                'evidence' => $firstEv !== '' ? $firstEv : null,
            ];
        }

        return [
            'classification' => 'missing',
            'matched_course_skill_id' => null,
            'reason' => "Kompetensi {$skillName} belum ditemukan dalam dokumen RPS.",
            'evidence' => null,
        ];
    }

    /**
     * @return array{title: string, reason: string, topics: list<string>, learning_keywords: list<string>}
     */
    private function generateRecommendation(string $documentText): array
    {
        $decoded = json_decode($documentText, true);
        $skill = is_array($decoded) && isset($decoded['skill']) ? (string) $decoded['skill'] : 'Kompetensi Teknis';
        $classification = is_array($decoded) && isset($decoded['classification']) ? (string) $decoded['classification'] : 'missing';
        $demand = is_array($decoded) && isset($decoded['demand_count']) ? (int) $decoded['demand_count'] : 1;
        $totalPostings = is_array($decoded) && isset($decoded['total_job_postings']) ? (int) $decoded['total_job_postings'] : 1;

        $statusLabel = $classification === 'partial'
            ? 'baru tercakup sebagian dalam RPS'
            : 'belum ditemukan dalam dokumen RPS';

        return [
            'title' => "Penguatan Kompetensi {$skill}",
            'reason' => "{$skill} dibutuhkan pada {$demand} dari {$totalPostings} lowongan terpilih, namun {$statusLabel}.",
            'topics' => [
                "Konsep fundamental dan arsitektur dasar {$skill}",
                "Praktik implementasi {$skill} pada studi kasus proyek nyata",
                "Pengujian, debugging, dan best practices penggunaan {$skill}",
            ],
            'learning_keywords' => [
                "{$skill} documentation and fundamentals",
                "{$skill} hands-on project tutorial",
                "{$skill} industry best practices",
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function splitPages(string $text): array
    {
        $pages = [];
        if (preg_match_all('/\[\[PAGE\s+(\d+)\]\]\s*(.*?)(?=\[\[PAGE\s+\d+\]\]|\z)/is', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $pages[(int) $match[1]] = trim($match[2]);
            }
        }

        if ($pages === []) {
            $pages[1] = trim($text);
        }

        return $pages;
    }

    /**
     * Split text into clean verbatim sentences/clauses suitable as substring evidence.
     *
     * @return list<string>
     */
    private function splitIntoEvidenceSegments(string $text): array
    {
        $rawParts = preg_split('/(?<=[.!?])\s+|\R+/u', $text) ?: [];
        $segments = [];

        foreach ($rawParts as $part) {
            $cleaned = trim((string) preg_replace('/^\[\[(?:PAGE|HALAMAN)\s+\d+\]\]\s*/iu', '', trim($part)));
            if (mb_strlen($cleaned) >= 8) {
                $segments[] = $cleaned;
            }
        }

        if ($segments === [] && trim($text) !== '') {
            $segments[] = trim($text);
        }

        return $segments;
    }

    private function detectSourceType(string $segment): string
    {
        if (preg_match('/\bSub[-\s]?CPMK\b/iu', $segment)) {
            return 'Sub-CPMK';
        }
        if (preg_match('/\bCPMK\b/iu', $segment)) {
            return 'CPMK';
        }
        if (preg_match('/\bCPL\b/iu', $segment)) {
            return 'CPL';
        }
        if (preg_match('/\b(?:Minggu|Pertemuan|Pekan)\s+\d+/iu', $segment)) {
            return 'weekly_plan';
        }
        if (preg_match('/\b(?:Tugas|Proyek|Praktikum|Evaluasi|Ujian)\b/iu', $segment)) {
            return 'assignment';
        }

        return 'material';
    }

    private function detectSourceReference(string $segment, string $sourceType): ?string
    {
        if (preg_match('/\b(Sub[-\s]?CPMK[-\s]?\d+|CPMK[-\s]?\d+|CPL[-\s]?\d+)\b/iu', $segment, $m)) {
            return $m[1];
        }

        return $sourceType !== 'material' ? $sourceType : null;
    }

    private function detectWeek(string $segment): ?int
    {
        if (preg_match('/\b(?:Minggu|Pertemuan|Pekan)\s*(?:ke[-\s]?)?(\d{1,2})\b/iu', $segment, $m)) {
            $w = (int) $m[1];

            return ($w >= 1 && $w <= 16) ? $w : null;
        }

        return null;
    }

    private function containsTerm(string $haystackLower, string $termLower): bool
    {
        if ($termLower === '') {
            return false;
        }

        $escaped = preg_quote($termLower, '/');

        return preg_match('/(?<![\p{L}\p{N}])'.$escaped.'(?![\p{L}\p{N}])/iu', $haystackLower) === 1;
    }

    /**
     * Return merged skill catalog from database (if available) and SkillSeeder::dictionary().
     *
     * @return list<array{canonical: string, category: ?string, terms: list<string>}>
     */
    private function skillCatalog(): array
    {
        $map = [];

        foreach (SkillSeeder::dictionary() as $canonical => [$category, $aliases]) {
            $terms = array_values(array_unique(array_map(
                'mb_strtolower',
                array_merge([$canonical], $aliases)
            )));
            // Sort longest terms first for specific matching
            usort($terms, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

            $map[mb_strtolower($canonical)] = [
                'canonical' => $canonical,
                'category' => $category instanceof SkillCategory ? $category->value : (string) $category,
                'terms' => $terms,
            ];
        }

        try {
            foreach (Skill::all() as $dbSkill) {
                $key = mb_strtolower($dbSkill->canonical_name);
                $existingTerms = $map[$key]['terms'] ?? [];
                $dbTerms = array_map(
                    'mb_strtolower',
                    array_merge([$dbSkill->canonical_name], $dbSkill->aliases ?? [])
                );
                $merged = array_values(array_unique(array_merge($existingTerms, $dbTerms)));
                usort($merged, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

                $map[$key] = [
                    'canonical' => $dbSkill->canonical_name,
                    'category' => $dbSkill->category?->value ?? ($map[$key]['category'] ?? 'other'),
                    'terms' => $merged,
                ];
            }
        } catch (Throwable) {
            // Fallback to static seeder dictionary when DB is not migrated/connected
        }

        return array_values($map);
    }
}
