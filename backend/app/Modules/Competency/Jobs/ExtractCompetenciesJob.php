<?php

declare(strict_types=1);

namespace App\Modules\Competency\Jobs;

use App\Enums\CompetencyOrigin;
use App\Enums\CompetencyReviewStatus;
use App\Enums\CompetencySourceType;
use App\Enums\SkillCategory;
use App\Enums\VerificationStatus;
use App\Models\CourseSkill;
use App\Models\RpsDocument;
use App\Modules\Ai\EmbeddingService;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\GeminiClient;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Competency\Services\SkillNormalizer;
use App\Modules\Rps\Enums\ExtractionStatus;
use App\Modules\Rps\Enums\ParseStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExtractCompetenciesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $rpsDocumentId,
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
        return [(new WithoutOverlapping((string) $this->rpsDocumentId))->dontRelease()];
    }

    public function handle(
        GeminiClient $gemini,
        SkillNormalizer $skillNormalizer,
        EvidenceVerifier $evidenceVerifier,
        ?EmbeddingService $embeddingService = null,
    ): void {
        $embeddingService ??= app(EmbeddingService::class);
        $document = RpsDocument::find($this->rpsDocumentId);

        if ($document === null || $document->parse_status !== ParseStatus::Parsed) {
            return;
        }

        if ($document->extraction_status === ExtractionStatus::Completed) {
            return;
        }

        $document->update([
            'extraction_status' => ExtractionStatus::Processing,
            'error_message' => null,
        ]);

        $pages = $document->pages()->orderBy('page_number')->get();
        if ($pages->isEmpty()) {
            $document->update([
                'extraction_status' => ExtractionStatus::Failed,
                'error_message' => 'Dokumen tidak memiliki halaman teks untuk diekstrak.',
            ]);

            return;
        }

        $mode = strtolower(trim((string) config('skillbridge.gemini.mode', 'gemini')));
        $key = config('skillbridge.gemini.api_key');
        $model = config('skillbridge.gemini.flash_model');
        $timeout = config('skillbridge.gemini.timeout_seconds');

        if (! in_array($mode, ['offline', 'fallback', 'auto'], true)
            && (! is_string($key) || trim($key) === ''
                || ! is_string($model) || ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,254}\z/', $model)
                || ! is_int($timeout) || $timeout < 1)) {
            $document->update([
                'extraction_status' => ExtractionStatus::Failed,
                'error_message' => 'Layanan AI belum dikonfigurasi dengan benar.',
            ]);

            return;
        }

        $promptPath = resource_path('prompts/rps-extract-v1.txt');
        $template = file_exists($promptPath)
            ? (string) file_get_contents($promptPath)
            : "Anda mengekstraksi kompetensi dari dokumen RPS:\n<document>\n{{pages_text}}\n</document>";

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'course' => ['type' => 'STRING', 'nullable' => true],
                'competencies' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'skill' => ['type' => 'STRING'],
                            'category' => [
                                'type' => 'STRING',
                                'enum' => ['language', 'framework', 'tool', 'concept', 'practice', 'other'],
                            ],
                            'competency' => ['type' => 'STRING'],
                            'evidence' => ['type' => 'STRING'],
                            'source_type' => [
                                'type' => 'STRING',
                                'enum' => ['CPL', 'CPMK', 'Sub-CPMK', 'material', 'weekly_plan', 'assignment'],
                            ],
                            'source_reference' => ['type' => 'STRING', 'nullable' => true],
                            'week' => ['type' => 'INTEGER', 'nullable' => true],
                            'page' => ['type' => 'INTEGER', 'nullable' => true],
                        ],
                        'required' => ['skill', 'category', 'competency', 'evidence', 'source_type'],
                    ],
                ],
            ],
            'required' => ['competencies'],
        ];

        /** @var list<array{skill: string, category: SkillCategory, competency: string, evidence: string, source_type: CompetencySourceType, source_reference: ?string, week: ?int, page: ?int}> $extractedItems */
        $extractedItems = [];

        try {
            // Process pages in chunks of 15 to stay comfortably within context boundaries
            $chunks = $pages->chunk(15);

            foreach ($chunks as $chunk) {
                $pageSections = [];
                foreach ($chunk as $page) {
                    $pageSections[] = "[[PAGE {$page->page_number}]]\n".trim((string) $page->text);
                }
                $pagesText = implode("\n\n", $pageSections);

                if (trim($pagesText) === '') {
                    continue;
                }

                $prompt = str_replace(['{{pages_text}}', '{{text}}'], $pagesText, $template);
                if (strlen($prompt) > 100_000) {
                    $prompt = mb_substr($prompt, 0, 100_000);
                }

                $response = $gemini->generateContent(
                    purpose: 'rps_extraction',
                    prompt: $prompt,
                    promptVersion: 'rps-extract-v1',
                    subject: $document,
                    responseSchema: $schema,
                );

                $rawText = $this->extractCandidateText($response);
                if ($rawText === null) {
                    throw new GeminiException('Respons AI tidak mengandung teks.');
                }

                $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($decoded) || ! isset($decoded['competencies']) || ! is_array($decoded['competencies'])) {
                    throw new GeminiException('Format JSON hasil ekstraksi AI tidak valid.');
                }

                $validatedChunk = $this->validateCompetencies($decoded['competencies']);
                if ($validatedChunk === null) {
                    throw new GeminiException('Skema kompetensi hasil ekstraksi AI tidak valid.');
                }

                foreach ($validatedChunk as $item) {
                    $extractedItems[] = $item;
                }
            }

            // Step 2 & 3: Evidence verification and Skill normalization
            $dedupedMap = [];

            foreach ($extractedItems as $item) {
                // Evidence verification
                $verification = $evidenceVerifier->verifyDocument($item['evidence'], $item['page'], $document);
                $isVerified = $verification->isVerified();
                $verificationStatus = $isVerified ? VerificationStatus::Verified : VerificationStatus::Unverified;
                // Rule 7: unverified -> page is not trusted (set null)
                $matchedPage = $isVerified ? $verification->page : null;

                // Skill normalization
                $skill = $skillNormalizer->normalize($item['skill'], $item['category']);
                $skillId = $skill?->id;
                $category = $skill?->category ?? $item['category'];

                // Step 4: Deduplication by skill_id (or raw skill) + source_reference
                $rawSourceRef = $item['source_reference'] !== null ? trim($item['source_reference']) : '';
                $dedupKey = ($skillId !== null ? "id:{$skillId}" : 'raw:'.mb_strtolower(trim($item['skill'])))
                    .'|'.mb_strtolower($rawSourceRef);

                $record = [
                    'skill_id' => $skillId,
                    'skill' => $item['skill'],
                    'category' => $category,
                    'competency' => $item['competency'],
                    'evidence' => $item['evidence'],
                    'source_type' => $item['source_type'],
                    'source_reference' => $item['source_reference'],
                    'week' => $item['week'],
                    'page' => $matchedPage,
                    'verification_status' => $verificationStatus,
                ];

                if (! isset($dedupedMap[$dedupKey])) {
                    $dedupedMap[$dedupKey] = $record;
                } elseif ($dedupedMap[$dedupKey]['verification_status'] === VerificationStatus::Unverified
                    && $verificationStatus === VerificationStatus::Verified) {
                    // Prefer verified over unverified
                    $dedupedMap[$dedupKey] = $record;
                }
            }

            // Step 5: Save to course_skills inside transaction (idempotent)
            DB::transaction(function () use ($document, $dedupedMap): void {
                $document->courseSkills()->where('origin', CompetencyOrigin::Ai)->delete();

                foreach ($dedupedMap as $record) {
                    CourseSkill::create([
                        'rps_document_id' => $document->id,
                        'skill_id' => $record['skill_id'],
                        'skill' => $record['skill'],
                        'category' => $record['category'],
                        'competency' => $record['competency'],
                        'evidence' => $record['evidence'],
                        'source_type' => $record['source_type'],
                        'origin' => CompetencyOrigin::Ai,
                        'review_status' => CompetencyReviewStatus::Pending,
                        'user_edited' => false,
                        'source_reference' => $record['source_reference'],
                        'week' => $record['week'],
                        'page' => $record['page'],
                        'verification_status' => $record['verification_status'],
                        'embedding' => null,
                        'embedding_model' => null,
                        'embedding_dimensions' => null,
                        'text_hash' => null,
                    ]);
                }
            });

            // Step 6: Generate and attach embeddings (T4.3)
            $courseSkills = $document->courseSkills()->with('skillEntry')->get();
            if ($courseSkills->isNotEmpty()) {
                $embeddingService->embedCourseSkills($courseSkills, $document);
            }

            $document->update([
                'extraction_status' => ExtractionStatus::Completed,
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $document->update([
                'extraction_status' => ExtractionStatus::Failed,
                'error_message' => 'Gagal mengekstrak kompetensi dari dokumen RPS. Silakan coba beberapa saat lagi.',
            ]);

            if (! app()->runningUnitTests()) {
                throw $e;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $document = RpsDocument::find($this->rpsDocumentId);

        if ($document !== null && $document->extraction_status !== ExtractionStatus::Completed) {
            $document->update([
                'extraction_status' => ExtractionStatus::Failed,
                'error_message' => 'Gagal mengekstrak kompetensi dari dokumen RPS. Silakan coba beberapa saat lagi.',
            ]);
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
     * @return list<array{skill: string, category: SkillCategory, competency: string, evidence: string, source_type: CompetencySourceType, source_reference: ?string, week: ?int, page: ?int}>|null
     */
    private function validateCompetencies(array $items): ?array
    {
        $sourceTypeMap = [
            'cpl' => CompetencySourceType::Cpl,
            'cpmk' => CompetencySourceType::Cpmk,
            'sub-cpmk' => CompetencySourceType::SubCpmk,
            'sub_cpmk' => CompetencySourceType::SubCpmk,
            'subcpmk' => CompetencySourceType::SubCpmk,
            'material' => CompetencySourceType::Material,
            'weekly_plan' => CompetencySourceType::WeeklyPlan,
            'weekly-plan' => CompetencySourceType::WeeklyPlan,
            'assignment' => CompetencySourceType::Assignment,
        ];

        $validated = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                return null;
            }

            $skill = $item['skill'] ?? null;
            if (! is_string($skill) || trim($skill) === '' || mb_strlen($skill) > 255) {
                return null;
            }

            $competency = $item['competency'] ?? null;
            if (! is_string($competency) || trim($competency) === '') {
                return null;
            }

            $evidence = $item['evidence'] ?? null;
            if (! is_string($evidence) || trim($evidence) === '') {
                return null;
            }

            $sourceTypeRaw = $item['source_type'] ?? null;
            if (! is_string($sourceTypeRaw)) {
                return null;
            }

            $sourceType = $sourceTypeMap[mb_strtolower(trim($sourceTypeRaw))] ?? null;
            if ($sourceType === null) {
                return null;
            }

            $categoryRaw = $item['category'] ?? 'other';
            $category = is_string($categoryRaw)
                ? (SkillCategory::tryFrom(mb_strtolower(trim($categoryRaw))) ?? SkillCategory::Other)
                : SkillCategory::Other;

            $sourceRef = $item['source_reference'] ?? null;
            if ($sourceRef !== null && (! is_string($sourceRef) || mb_strlen($sourceRef) > 255)) {
                return null;
            }

            $week = $item['week'] ?? null;
            if ($week !== null && (! is_int($week) || $week < 0 || $week > 52)) {
                return null;
            }

            $page = $item['page'] ?? null;
            if ($page !== null && (! is_int($page) || $page < 1)) {
                return null;
            }

            $validated[] = [
                'skill' => trim($skill),
                'category' => $category,
                'competency' => trim($competency),
                'evidence' => trim($evidence),
                'source_type' => $sourceType,
                'source_reference' => $sourceRef !== null ? trim($sourceRef) : null,
                'week' => $week,
                'page' => $page,
            ];
        }

        return $validated;
    }
}
