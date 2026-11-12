<?php

declare(strict_types=1);

namespace App\Modules\Recommendation\Jobs;

use App\Enums\AnalysisStatus;
use App\Enums\MatchClassification;
use App\Models\Analysis;
use App\Models\Recommendation;
use App\Models\SkillMatch;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\GeminiClient;
use App\Modules\Recommendation\Services\PriorityCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GenerateRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $analysisId,
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
        return [(new WithoutOverlapping((string) $this->analysisId))->dontRelease()];
    }

    public function handle(
        GeminiClient $gemini,
        PriorityCalculator $priorityCalculator,
    ): void {
        $analysis = Analysis::with(['jobPostings', 'skillMatches.skill', 'skillMatches.courseSkill'])
            ->find($this->analysisId);

        if ($analysis === null || $analysis->status !== AnalysisStatus::Completed) {
            return;
        }

        $selectedPostings = $analysis->jobPostings->count();
        if ($selectedPostings === 0) {
            return;
        }

        // Exclude self-declared user skills if user_skills table exists (T7.5)
        $userSkillIds = [];
        if (Schema::hasTable('user_skills')) {
            $userSkillIds = DB::table('user_skills')
                ->where('user_id', $analysis->user_id)
                ->pluck('skill_id')
                ->map(fn ($id): int => (int) $id)
                ->toArray();
        }

        // Get missing and partial skill matches not declared by user
        $targetMatches = $analysis->skillMatches->filter(function (SkillMatch $match) use ($userSkillIds): bool {
            if ($match->classification === MatchClassification::Covered) {
                return false;
            }

            return ! in_array((int) $match->skill_id, $userSkillIds, true);
        })->values();

        if ($targetMatches->isEmpty()) {
            $analysis->recommendations()->delete();

            return;
        }

        $coveredSkills = $analysis->skillMatches
            ->where('classification', MatchClassification::Covered)
            ->map(fn (SkillMatch $m): string => $m->skill->canonical_name)
            ->values()
            ->toArray();

        $promptPath = resource_path('prompts/reco-v1.txt');
        $template = file_exists($promptPath)
            ? (string) file_get_contents($promptPath)
            : "Susun rekomendasi belajar:\n<data>{{skill_match_with_context}}</data>";

        $responseSchema = [
            'type' => 'OBJECT',
            'properties' => [
                'title' => ['type' => 'STRING'],
                'reason' => ['type' => 'STRING'],
                'topics' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
                'learning_keywords' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
            ],
            'required' => ['title', 'reason', 'topics', 'learning_keywords'],
        ];

        $recommendationsToSave = [];

        foreach ($targetMatches as $match) {
            $priority = $priorityCalculator->calculate($match->classification, $match->demand_count, $selectedPostings);
            if ($priority === null) {
                continue;
            }

            $contextData = [
                'skill' => $match->skill->canonical_name,
                'category' => $match->skill->category?->value ?? 'other',
                'classification' => $match->classification->value,
                'demand_count' => $match->demand_count,
                'total_job_postings' => $selectedPostings,
                'matching_reason' => $match->reason,
                'partial_evidence' => $match->evidence,
                'covered_skills_in_rps' => $coveredSkills,
            ];

            $prompt = str_replace(
                '{{skill_match_with_context}}',
                (string) json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                $template
            );

            try {
                $response = $gemini->generateContent(
                    purpose: 'recommendation',
                    prompt: $prompt,
                    promptVersion: 'reco-v1',
                    subject: $analysis,
                    responseSchema: $responseSchema,
                );

                $rawText = $this->extractCandidateText($response);
                if ($rawText === null) {
                    throw new GeminiException('Respons rekomendasi tidak memiliki teks.');
                }

                $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($decoded)) {
                    throw new GeminiException('Format JSON rekomendasi tidak valid.');
                }

                $title = $this->stripUrls((string) ($decoded['title'] ?? "Panduan {$match->skill->canonical_name}"));
                $reason = $this->stripUrls((string) ($decoded['reason'] ?? $match->reason));

                $topics = array_values(array_filter(
                    array_map(fn ($t) => $this->stripUrls((string) $t), (array) ($decoded['topics'] ?? []))
                ));
                $keywords = array_values(array_filter(
                    array_map(fn ($k) => $this->stripUrls((string) $k), (array) ($decoded['learning_keywords'] ?? []))
                ));

                $recommendationsToSave[] = [
                    'analysis_id' => $analysis->id,
                    'skill_match_id' => $match->id,
                    'title' => $title !== '' ? $title : "Panduan Belajar {$match->skill->canonical_name}",
                    'reason' => $reason !== '' ? $reason : $match->reason,
                    'priority' => $priority,
                    'details' => [
                        'topics' => ! empty($topics) ? $topics : [$match->skill->canonical_name],
                        'learning_keywords' => ! empty($keywords) ? $keywords : [$match->skill->canonical_name],
                    ],
                ];
            } catch (Throwable) {
                // Fallback recommendation when LLM fails
                $recommendationsToSave[] = [
                    'analysis_id' => $analysis->id,
                    'skill_match_id' => $match->id,
                    'title' => "Fundamental {$match->skill->canonical_name}",
                    'reason' => $match->reason,
                    'priority' => $priority,
                    'details' => [
                        'topics' => ["Dasar dan konsep {$match->skill->canonical_name}"],
                        'learning_keywords' => ["{$match->skill->canonical_name} tutorial"],
                    ],
                ];
            }
        }

        // Idempotent replacement in transaction
        DB::transaction(function () use ($analysis, $recommendationsToSave): void {
            $analysis->recommendations()->delete();

            foreach ($recommendationsToSave as $record) {
                Recommendation::create($record);
            }
        });
    }

    /**
     * Remove URLs strictly from generated text (anti-hallucination guardrail).
     */
    private function stripUrls(string $text): string
    {
        $cleaned = preg_replace('/https?:\/\/[^\s]+|www\.[^\s]+/i', '', $text);
        $cleaned = (string) preg_replace('/\s+/u', ' ', $cleaned ?? '');

        return trim($cleaned);
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
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                return $part['text'];
            }
        }

        return null;
    }
}
