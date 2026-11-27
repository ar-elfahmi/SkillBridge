<?php

declare(strict_types=1);

namespace App\Modules\Recommendation\Jobs;

use App\Enums\AnalysisStatus;
use App\Enums\MatchClassification;
use App\Enums\RecommendationPriority;
use App\Models\Analysis;
use App\Models\Recommendation;
use App\Models\SkillMatch;
use App\Modules\Ai\Concerns\HasGeminiJobSettings;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
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
use Illuminate\Support\Sleep;
use Throwable;

class GenerateRecommendationsJob implements ShouldQueue
{
    use Dispatchable, HasGeminiJobSettings, InteractsWithQueue, Queueable, SerializesModels;

    public const PROMPT_VERSION = 'reco-v3';

    // Legacy upper bound: retryUntil() (see HasGeminiJobSettings) takes precedence while it is defined.
    public int $tries = 3;

    public function __construct(
        public int $analysisId,
    ) {
        // Several Gemini calls (one per skill batch), so this job gets the long timeout.
        $this->configureGeminiJob(long: true);
    }

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
        return [(new WithoutOverlapping((string) $this->analysisId))->dontRelease()->expireAfter($this->overlapLockSeconds())];
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

        // Priority is decided by Laravel only; skills without a priority get no recommendation.
        /** @var array<int, array{match: SkillMatch, priority: RecommendationPriority}> $items */
        $items = [];
        foreach ($targetMatches as $match) {
            $priority = $priorityCalculator->calculate($match->classification, $match->demand_count, $selectedPostings);
            if ($priority !== null) {
                $items[$match->id] = ['match' => $match, 'priority' => $priority];
            }
        }

        $generated = $this->generate($gemini, $analysis, $items, $coveredSkills, $selectedPostings);

        $recommendationsToSave = [];
        foreach ($items as $matchId => $item) {
            $match = $item['match'];
            $skillName = $match->skill->canonical_name;
            $decoded = $generated[$matchId] ?? null;

            if ($decoded === null) {
                // Fallback recommendation when the LLM gave no usable answer for this skill.
                $recommendationsToSave[] = [
                    'analysis_id' => $analysis->id,
                    'skill_match_id' => $match->id,
                    'title' => "Fundamental {$skillName}",
                    'reason' => $match->reason,
                    'priority' => $item['priority'],
                    'details' => [
                        'topics' => ["Dasar dan konsep {$skillName}"],
                        'learning_keywords' => ["{$skillName} tutorial"],
                    ],
                ];

                continue;
            }

            $title = $this->stripUrls((string) ($decoded['title'] ?? "Panduan {$skillName}"));
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
                'title' => $title !== '' ? $title : "Panduan Belajar {$skillName}",
                'reason' => $reason !== '' ? $reason : $match->reason,
                'priority' => $item['priority'],
                'details' => [
                    'topics' => ! empty($topics) ? $topics : [$skillName],
                    'learning_keywords' => ! empty($keywords) ? $keywords : [$skillName],
                ],
            ];
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
     * Ask Gemini for the recommendations, several skills per prompt and batches in parallel.
     *
     * Only validated entries are returned (id belongs to the batch, no duplicates). Ids that stay
     * without an answer after the allowed retries are simply absent, and the caller writes the
     * deterministic fallback for them. A daily quota or a minute budget that does not free up
     * stops further calls instead of failing the whole job.
     *
     * @param  array<int, array{match: SkillMatch, priority: RecommendationPriority}>  $items
     * @param  list<string>  $coveredSkills
     * @return array<int, array<string, mixed>> decoded recommendation per skill_match id
     */
    private function generate(
        GeminiClient $gemini,
        Analysis $analysis,
        array $items,
        array $coveredSkills,
        int $selectedPostings,
    ): array {
        $promptPath = resource_path('prompts/'.self::PROMPT_VERSION.'.txt');
        $template = file_exists($promptPath)
            ? (string) file_get_contents($promptPath)
            : "Susun rekomendasi belajar:\n<data>{{skill_match_with_context}}</data>";

        $batchSize = max(1, (int) config('skillbridge.gemini.recommendation_batch_size', 5));
        $concurrency = max(1, (int) config('skillbridge.gemini.max_concurrent', 3));
        $maxRetries = max(0, (int) config('skillbridge.gemini.chunk_retries', 2));
        $maxWaits = max(0, (int) config('skillbridge.gemini.jobs.max_rate_limit_waits', 3));

        /** @var array<int, array<string, mixed>> $generated */
        $generated = [];
        $pending = array_keys($items);
        $attempt = 0;
        $waits = 0;

        while ($pending !== []) {
            $batches = array_chunk($pending, $batchSize);
            $requests = [];
            foreach ($batches as $index => $ids) {
                $requests['batch-'.$index] = [
                    'prompt' => $this->buildPrompt($template, $ids, $items, $coveredSkills, $selectedPostings),
                    'prompt_version' => self::PROMPT_VERSION,
                    'subject' => $analysis,
                    'response_schema' => $this->responseSchema(),
                ];
            }

            try {
                $responses = $gemini->generateContentConcurrent('recommendation', $requests, $concurrency);
            } catch (Throwable) {
                return $generated;
            }

            $next = [];
            $rateLimited = null;
            $generatedBefore = count($generated);

            foreach ($batches as $index => $ids) {
                $response = $responses['batch-'.$index] ?? new GeminiException;

                if ($response instanceof GeminiQuotaExhaustedException) {
                    // Daily budget is gone: keep what we have, the rest uses the fallback text.
                    return $generated;
                }

                if ($response instanceof GeminiRateLimitedException) {
                    $rateLimited = $rateLimited === null || $response->retryAfterSeconds > $rateLimited->retryAfterSeconds
                        ? $response
                        : $rateLimited;
                    array_push($next, ...$ids);

                    continue;
                }

                $valid = $response instanceof GeminiException ? [] : $this->parseBatch($response, $ids);

                foreach ($valid as $matchId => $decoded) {
                    $generated[$matchId] = $decoded;
                }

                foreach ($ids as $matchId) {
                    if (! isset($generated[$matchId])) {
                        $next[] = $matchId;
                    }
                }
            }

            $pending = $next;

            if ($pending === []) {
                break;
            }

            // Waits are only counted while nothing progresses.
            if (count($generated) > $generatedBefore) {
                $waits = 0;
            }

            if ($rateLimited !== null) {
                if ($waits >= $maxWaits) {
                    break;
                }

                $waits++;
                Sleep::for($rateLimited->retryAfterSeconds)->seconds();

                continue;
            }

            $attempt++;
            if ($attempt > $maxRetries) {
                break;
            }
        }

        return $generated;
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, array{match: SkillMatch, priority: RecommendationPriority}>  $items
     * @param  list<string>  $coveredSkills
     */
    private function buildPrompt(string $template, array $ids, array $items, array $coveredSkills, int $selectedPostings): string
    {
        $entries = [];
        foreach ($ids as $id) {
            $match = $items[$id]['match'];
            $entries[] = [
                'id' => $match->id,
                'skill' => $match->skill->canonical_name,
                'category' => $match->skill->category?->value ?? 'other',
                'classification' => $match->classification->value,
                'demand_count' => $match->demand_count,
                'matching_reason' => $match->reason,
                'partial_evidence' => $match->evidence,
            ];
        }

        $contextData = [
            'total_job_postings' => $selectedPostings,
            'covered_skills_in_rps' => $coveredSkills,
            'items' => $entries,
        ];

        return str_replace(
            '{{skill_match_with_context}}',
            (string) json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            $template
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'id' => ['type' => 'INTEGER'],
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
                'required' => ['id', 'title', 'reason', 'topics', 'learning_keywords'],
            ],
        ];
    }

    /**
     * Accept only entries whose id belongs to this batch, once each. Anything else is ignored,
     * so an invented or repeated id can never reach another skill's recommendation.
     *
     * @param  array<string, mixed>  $response
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function parseBatch(array $response, array $ids): array
    {
        $rawText = $this->extractCandidateText($response);
        if ($rawText === null) {
            return [];
        }

        try {
            $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $valid = [];
        foreach ($decoded as $entry) {
            if (! is_array($entry) || ! isset($entry['id']) || ! is_numeric($entry['id'])) {
                continue;
            }

            $id = (int) $entry['id'];
            if (! in_array($id, $ids, true) || isset($valid[$id])) {
                continue;
            }

            $valid[$id] = $entry;
        }

        return $valid;
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
