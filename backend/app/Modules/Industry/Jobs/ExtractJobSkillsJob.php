<?php

declare(strict_types=1);

namespace App\Modules\Industry\Jobs;

use App\Enums\SkillCategory;
use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Modules\Ai\EmbeddingService;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\GeminiClient;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExtractJobSkillsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $jobPostingId,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->jobPostingId))->dontRelease()];
    }

    public function handle(
        GeminiClient $gemini,
        SkillNormalizer $skillNormalizer,
        EvidenceVerifier $evidenceVerifier,
        ?EmbeddingService $embeddingService = null,
    ): void {
        $embeddingService ??= app(EmbeddingService::class);

        $posting = JobPosting::find($this->jobPostingId);
        if ($posting === null) {
            return;
        }

        if ($posting->extraction_status === ExtractionStatus::Completed) {
            return;
        }

        $posting->update(['extraction_status' => ExtractionStatus::Processing]);

        $combinedText = trim(($posting->description ?? '')."\n\n".($posting->qualifications ?? ''));
        if ($combinedText === '') {
            $posting->update(['extraction_status' => ExtractionStatus::Completed]);

            return;
        }

        $mode = strtolower(trim((string) config('skillbridge.gemini.mode', 'gemini')));
        $key = config('skillbridge.gemini.api_key');
        $model = config('skillbridge.gemini.flash_model');
        $timeout = config('skillbridge.gemini.timeout_seconds');

        if (! in_array($mode, ['offline', 'fallback', 'auto'], true)
            && (! is_string($key) || trim($key) === ''
                || ! is_string($model) || ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._\/-]{0,254}\z/', $model)
                || ! is_int($timeout) || $timeout < 1)) {
            $posting->update(['extraction_status' => ExtractionStatus::Failed]);

            return;
        }

        $promptPath = resource_path('prompts/job-extract-v1.txt');
        $template = file_exists($promptPath)
            ? (string) file_get_contents($promptPath)
            : "Ekstraksi skill dari lowongan:\n<job>\n{{description_and_qualifications}}\n</job>";

        $prompt = str_replace(
            ['{{description_and_qualifications}}', '{{text}}'],
            $combinedText,
            $template
        );

        if (strlen($prompt) > 100_000) {
            $prompt = mb_substr($prompt, 0, 100_000);
        }

        $schema = [
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

        try {
            $response = $gemini->generateContent(
                purpose: 'job_extraction',
                prompt: $prompt,
                promptVersion: 'job-extract-v1',
                subject: $posting,
                responseSchema: $schema,
            );

            $rawText = $this->extractCandidateText($response);
            if ($rawText === null) {
                throw new GeminiException('Respons AI tidak mengandung teks.');
            }

            $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || ! isset($decoded['skills']) || ! is_array($decoded['skills'])) {
                throw new GeminiException('Format JSON hasil ekstraksi lowongan tidak valid.');
            }

            $validatedSkills = $this->validateSkills($decoded['skills']);
            if ($validatedSkills === null) {
                throw new GeminiException('Skema skill hasil ekstraksi tidak valid.');
            }

            $normalizedCombined = mb_strtolower($evidenceVerifier->normalizeText($combinedText));
            $dedupedMap = [];

            foreach ($validatedSkills as $item) {
                // Verify evidence substring (anti-hallucination)
                $normalizedEvidence = mb_strtolower($evidenceVerifier->normalizeText($item['evidence']));
                if ($normalizedEvidence === '' || ! str_contains($normalizedCombined, $normalizedEvidence)) {
                    continue;
                }

                // Normalize skill to canonical dictionary
                $skill = $skillNormalizer->normalize($item['skill'], $item['category']);
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
                if (! isset($dedupedMap[$skill->id])) {
                    $dedupedMap[$skill->id] = $record;
                } elseif (mb_strlen($item['requirement_text']) > mb_strlen($dedupedMap[$skill->id]['requirement_text'])) {
                    $dedupedMap[$skill->id] = $record;
                }
            }

            // Save to job_skills in transaction (idempotent)
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

            // Embed job skills via EmbeddingService (T4.1)
            $jobSkills = $posting->jobSkills()->with('skillEntry')->get();
            if ($jobSkills->isNotEmpty()) {
                $embeddingService->embedJobSkills($jobSkills, $posting);
            }

            $posting->update(['extraction_status' => ExtractionStatus::Completed]);
        } catch (Throwable $e) {
            $posting->update(['extraction_status' => ExtractionStatus::Failed]);

            if (! app()->runningUnitTests()) {
                throw $e;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $posting = JobPosting::find($this->jobPostingId);
        if ($posting !== null && $posting->extraction_status !== ExtractionStatus::Completed) {
            $posting->update(['extraction_status' => ExtractionStatus::Failed]);
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractCandidateText(array $response): ?string
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
