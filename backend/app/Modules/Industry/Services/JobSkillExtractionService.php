<?php

declare(strict_types=1);

namespace App\Modules\Industry\Services;

use App\Enums\SkillCategory;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Everything about turning one job posting into validated, saved job skills, independent of how
 * the Gemini call is made (single job or grouped job). Embeddings are created by the caller.
 */
class JobSkillExtractionService
{
    public const PROMPT_VERSION = 'job-extract-v1';

    public function __construct(
        private readonly SkillNormalizer $skillNormalizer,
        private readonly EvidenceVerifier $evidenceVerifier,
    ) {}

    public function combinedText(JobPosting $posting): string
    {
        return trim(($posting->description ?? '')."\n\n".($posting->qualifications ?? ''));
    }

    /**
     * @return array{prompt: string, prompt_version: string, subject: JobPosting, response_schema: array<string, mixed>}
     */
    public function buildRequest(JobPosting $posting, string $combinedText): array
    {
        $promptPath = resource_path('prompts/job-extract-v1.txt');
        $template = file_exists($promptPath)
            ? (string) file_get_contents($promptPath)
            : "Ekstraksi skill dari lowongan:\n<job>\n{{description_and_qualifications}}\n</job>";

        $prompt = str_replace(['{{description_and_qualifications}}', '{{text}}'], $combinedText, $template);

        if (strlen($prompt) > 100_000) {
            $prompt = mb_substr($prompt, 0, 100_000);
        }

        return [
            'prompt' => $prompt,
            'prompt_version' => self::PROMPT_VERSION,
            'subject' => $posting,
            'response_schema' => $this->schema(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'skills' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'skill' => ['type' => 'STRING'],
                            'category' => [
                                'type' => 'STRING',
                                'enum' => ['language', 'framework', 'tool', 'concept', 'practice', 'other'],
                            ],
                            'requirement_text' => ['type' => 'STRING'],
                            'evidence' => ['type' => 'STRING'],
                        ],
                        'required' => ['skill', 'category', 'requirement_text', 'evidence'],
                    ],
                ],
            ],
            'required' => ['skills'],
        ];
    }

    /**
     * Decode and validate a Gemini envelope. Null means the response is unusable.
     *
     * @param  array<string, mixed>  $response
     * @return list<array{skill: string, category: SkillCategory, requirement_text: string, evidence: string}>|null
     */
    public function parse(array $response): ?array
    {
        $rawText = $this->candidateText($response);
        if ($rawText === null) {
            return null;
        }

        try {
            $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['skills']) || ! is_array($decoded['skills'])) {
            return null;
        }

        return $this->validateSkills($decoded['skills']);
    }

    /**
     * Verify evidence, normalize skills, deduplicate and save inside one transaction (idempotent).
     *
     * @param  list<array{skill: string, category: SkillCategory, requirement_text: string, evidence: string}>  $validatedSkills
     */
    public function store(JobPosting $posting, string $combinedText, array $validatedSkills): void
    {
        $normalizedCombined = mb_strtolower($this->evidenceVerifier->normalizeText($combinedText));
        $dedupedMap = [];

        foreach ($validatedSkills as $item) {
            // Verify evidence substring (anti-hallucination)
            $normalizedEvidence = mb_strtolower($this->evidenceVerifier->normalizeText($item['evidence']));
            if ($normalizedEvidence === '' || ! str_contains($normalizedCombined, $normalizedEvidence)) {
                continue;
            }

            // Normalize skill to canonical dictionary
            $skill = $this->skillNormalizer->normalize($item['skill'], $item['category']);
            if ($skill === null) {
                continue;
            }

            $record = [
                'skill_id' => $skill->id,
                'skill' => $item['skill'],
                'requirement_text' => $item['requirement_text'],
                'evidence' => $item['evidence'],
            ];

            // Deduplicate by skill_id, keeping the one with longer requirement_text
            if (! isset($dedupedMap[$skill->id])
                || mb_strlen($item['requirement_text']) > mb_strlen($dedupedMap[$skill->id]['requirement_text'])) {
                $dedupedMap[$skill->id] = $record;
            }
        }

        DB::transaction(function () use ($posting, $dedupedMap): void {
            $posting->jobSkills()->delete();

            foreach ($dedupedMap as $record) {
                JobSkill::create([
                    'job_posting_id' => $posting->id,
                    'skill_id' => $record['skill_id'],
                    'skill' => $record['skill'],
                    'requirement_text' => $record['requirement_text'],
                    'evidence' => $record['evidence'],
                    'embedding' => null,
                    'embedding_model' => null,
                    'embedding_dimensions' => null,
                    'text_hash' => null,
                ]);
            }
        });
    }

    /**
     * True when skills were saved by an earlier run but the embedding step never finished.
     */
    public function hasUnembeddedSkills(JobPosting $posting): bool
    {
        return $posting->jobSkills()->exists() && $posting->jobSkills()->whereNull('embedding')->exists();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function candidateText(array $response): ?string
    {
        $candidate = $response['candidates'][0] ?? null;
        if (! is_array($candidate)) {
            return null;
        }

        $parts = $candidate['content']['parts'] ?? null;
        if (! is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            if (is_array($part) && ($part['thought'] ?? false) !== true && is_string($part['text'] ?? null) && trim($part['text']) !== '') {
                return $part['text'];
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array{skill: string, category: SkillCategory, requirement_text: string, evidence: string}>|null
     */
    private function validateSkills(array $items): ?array
    {
        $validated = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                return null;
            }

            $skill = $item['skill'] ?? null;
            if (! is_string($skill) || trim($skill) === '' || mb_strlen($skill) > 255) {
                return null;
            }

            $reqText = $item['requirement_text'] ?? null;
            if (! is_string($reqText) || trim($reqText) === '') {
                return null;
            }

            $evidence = $item['evidence'] ?? null;
            if (! is_string($evidence) || trim($evidence) === '') {
                return null;
            }

            $categoryRaw = $item['category'] ?? 'other';
            $category = is_string($categoryRaw)
                ? (SkillCategory::tryFrom(mb_strtolower(trim($categoryRaw))) ?? SkillCategory::Other)
                : SkillCategory::Other;

            $validated[] = [
                'skill' => trim($skill),
                'category' => $category,
                'requirement_text' => trim($reqText),
                'evidence' => trim($evidence),
            ];
        }

        return $validated;
    }
}
