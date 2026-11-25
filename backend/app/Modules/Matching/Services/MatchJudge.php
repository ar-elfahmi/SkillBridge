<?php

declare(strict_types=1);

namespace App\Modules\Matching\Services;

use App\Enums\JudgedBy;
use App\Enums\MatchClassification;
use App\Models\Analysis;
use App\Models\CourseSkill;
use App\Models\JobSkill;
use App\Models\Skill;
use App\Modules\Ai\Exceptions\GeminiException;
use App\Modules\Ai\Exceptions\GeminiQuotaExhaustedException;
use App\Modules\Ai\Exceptions\GeminiRateLimitedException;
use App\Modules\Ai\GeminiClient;
use App\Modules\Ai\Services\RateLimitRetry;
use Illuminate\Support\Sleep;
use Throwable;

class MatchJudge
{
    public const PROMPT_VERSION = 'match-judge-v1';

    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly ClassificationGuardrails $guardrails,
    ) {}

    /**
     * Judge the coverage of an industry skill against top-K course skill candidates.
     *
     * @param  list<array{course_skill: CourseSkill, course_skill_id: int, score: float}>  $candidateList
     */
    public function judge(
        Skill $skill,
        JobSkill $representativeJobSkill,
        array $candidateList,
        ?Analysis $analysis = null,
    ): MatchJudgeResult {
        $prepared = $this->prepare($skill, $representativeJobSkill, $candidateList);

        if ($prepared['rule'] !== null) {
            return $prepared['rule'];
        }

        try {
            $response = RateLimitRetry::run(fn (): array => $this->gemini->generateContent(
                purpose: 'match_judge',
                prompt: $prepared['prompt'],
                promptVersion: self::PROMPT_VERSION,
                subject: $analysis,
                responseSchema: $this->responseSchema(),
            ));

            return $this->resolve($response, $skill, $prepared);
        } catch (GeminiQuotaExhaustedException|GeminiRateLimitedException $e) {
            // A missing quota is a system problem, never a coverage result: let the job decide.
            throw $e;
        } catch (Throwable $e) {
            return $this->failed($prepared, $e);
        }
    }

    /**
     * Judge many skills at once. Skills that need Gemini are sent in parallel (bounded by
     * `skillbridge.gemini.max_concurrent`), so one slow response no longer delays all the others.
     * Nothing is persisted here; the caller saves the results afterwards.
     *
     * Each id is retried on transport/provider failures (`skillbridge.gemini.chunk_retries`), then falls back
     * to `missing` exactly like the single-call path. A minute-budget wait pauses the whole batch and is
     * bounded by `skillbridge.gemini.jobs.max_rate_limit_waits`.
     *
     * @param  array<int, array{skill: Skill, representative: JobSkill, candidates: list<array{course_skill: CourseSkill, course_skill_id: int, score: float}>}>  $items  keyed by skill id
     * @return array<int, MatchJudgeResult> same keys as $items
     *
     * @throws GeminiRateLimitedException when the minute budget did not free up within the allowed waits
     * @throws GeminiQuotaExhaustedException when the daily budget is gone
     */
    public function judgeMany(array $items, ?Analysis $analysis = null): array
    {
        /** @var array<int, MatchJudgeResult> $results */
        $results = [];
        /** @var array<int, array{rule: ?MatchJudgeResult, prompt: string, bestScore: float, simFloor: float, candidateMap: array<int, CourseSkill>, candidatesForStorage: list<array{course_skill_id: int, score: float}>}> $pending */
        $pending = [];

        foreach ($items as $key => $item) {
            $prepared = $this->prepare($item['skill'], $item['representative'], $item['candidates']);

            if ($prepared['rule'] !== null) {
                $results[$key] = $prepared['rule'];
            } else {
                $pending[$key] = $prepared;
            }
        }

        $maxWaits = max(0, (int) config('skillbridge.gemini.jobs.max_rate_limit_waits', 3));
        $maxRetries = max(0, (int) config('skillbridge.gemini.chunk_retries', 2));
        $concurrency = max(1, (int) config('skillbridge.gemini.max_concurrent', 3));
        $waits = 0;
        /** @var array<int, int> $failures */
        $failures = [];

        while ($pending !== []) {
            $requests = [];
            foreach ($pending as $key => $prepared) {
                $requests['skill-'.$key] = [
                    'prompt' => $prepared['prompt'],
                    'prompt_version' => self::PROMPT_VERSION,
                    'subject' => $analysis,
                    'response_schema' => $this->responseSchema(),
                ];
            }

            $responses = $this->gemini->generateContentConcurrent('match_judge', $requests, $concurrency);

            $retry = [];
            $rateLimited = null;
            $resolvedBefore = count($results);

            foreach ($pending as $key => $prepared) {
                $response = $responses['skill-'.$key] ?? new GeminiException;

                if ($response instanceof GeminiQuotaExhaustedException) {
                    throw $response;
                }

                if ($response instanceof GeminiRateLimitedException) {
                    $rateLimited = $rateLimited === null || $response->retryAfterSeconds > $rateLimited->retryAfterSeconds
                        ? $response
                        : $rateLimited;
                    $retry[$key] = $prepared;

                    continue;
                }

                if ($response instanceof GeminiException) {
                    $failures[$key] = ($failures[$key] ?? 0) + 1;

                    if ($failures[$key] > $maxRetries) {
                        $results[$key] = $this->failed($prepared, $response);
                    } else {
                        $retry[$key] = $prepared;
                    }

                    continue;
                }

                try {
                    $results[$key] = $this->resolve($response, $items[$key]['skill'], $prepared);
                } catch (Throwable $e) {
                    $results[$key] = $this->failed($prepared, $e);
                }
            }

            // Waits are only counted while nothing progresses: a long list that needs several minute windows is fine.
            if (count($results) > $resolvedBefore) {
                $waits = 0;
            }

            if ($rateLimited !== null) {
                if ($waits >= $maxWaits) {
                    throw $rateLimited;
                }

                $waits++;
                Sleep::for($rateLimited->retryAfterSeconds)->seconds();
            }

            $pending = $retry;
        }

        // Keep the caller's order regardless of which request finished first.
        $ordered = [];
        foreach (array_keys($items) as $key) {
            $ordered[$key] = $results[$key];
        }

        return $ordered;
    }

    /**
     * Decide whether Gemini is needed at all and build the prompt when it is.
     *
     * @param  list<array{course_skill: CourseSkill, course_skill_id: int, score: float}>  $candidateList
     * @return array{rule: ?MatchJudgeResult, prompt: string, bestScore: float, simFloor: float, candidateMap: array<int, CourseSkill>, candidatesForStorage: list<array{course_skill_id: int, score: float}>}
     */
    private function prepare(Skill $skill, JobSkill $representativeJobSkill, array $candidateList): array
    {
        $simFloor = (float) config('skillbridge.matching.sim_floor', 0.50);

        $candidateMap = [];
        $candidatesForStorage = [];
        foreach ($candidateList as $item) {
            $candidateMap[$item['course_skill_id']] = $item['course_skill'];
            $candidatesForStorage[] = [
                'course_skill_id' => $item['course_skill_id'],
                'score' => round((float) $item['score'], 5),
            ];
        }

        $bestCandidate = $candidateList[0] ?? null;
        $bestScore = $bestCandidate !== null ? (float) $bestCandidate['score'] : 0.0;

        // Rule: If no candidates or best score is below SIM_FLOOR, mark as missing without LLM call
        if ($bestCandidate === null || $bestScore < $simFloor) {
            return [
                'rule' => new MatchJudgeResult(
                    classification: MatchClassification::Missing,
                    matchedCourseSkillId: null,
                    similarityScore: $bestCandidate !== null ? $bestScore : null,
                    reason: 'Tidak ditemukan kompetensi RPS yang memenuhi ambang batas kemiripan semantik minimum.',
                    evidence: null,
                    judgedBy: JudgedBy::Rule,
                    candidates: $candidatesForStorage,
                ),
                'prompt' => '',
                'bestScore' => $bestScore,
                'simFloor' => $simFloor,
                'candidateMap' => $candidateMap,
                'candidatesForStorage' => $candidatesForStorage,
            ];
        }

        // Build prompt with candidates
        $candidatesData = array_map(function (array $item): array {
            return [
                'id' => $item['course_skill_id'],
                'competency' => $item['course_skill']->competency,
                'evidence' => $item['course_skill']->evidence ?? '',
            ];
        }, $candidateList);

        $promptPath = resource_path('prompts/match-judge-v1.txt');
        $template = file_exists($promptPath)
            ? (string) file_get_contents($promptPath)
            : "Tentukan cakupan skill industri:\n<requirement>{{skill}}: {{requirement_text}}</requirement>\n<candidates>{{json_candidates_with_id_competency_evidence}}</candidates>";

        $prompt = str_replace(
            ['{{skill}}', '{{requirement_text}}', '{{json_candidates_with_id_competency_evidence}}'],
            [
                $skill->canonical_name,
                $representativeJobSkill->requirement_text,
                (string) json_encode($candidatesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ],
            $template
        );

        return [
            'rule' => null,
            'prompt' => $prompt,
            'bestScore' => $bestScore,
            'simFloor' => $simFloor,
            'candidateMap' => $candidateMap,
            'candidatesForStorage' => $candidatesForStorage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'classification' => [
                    'type' => 'STRING',
                    'enum' => ['covered', 'partial', 'missing'],
                ],
                'matched_course_skill_id' => [
                    'type' => 'INTEGER',
                ],
                'reason' => [
                    'type' => 'STRING',
                ],
                'evidence' => [
                    'type' => 'STRING',
                ],
            ],
            'required' => ['classification', 'reason'],
        ];
    }

    /**
     * Validate the provider envelope and apply the guardrails.
     *
     * @param  array<string, mixed>  $response
     * @param  array{rule: ?MatchJudgeResult, prompt: string, bestScore: float, simFloor: float, candidateMap: array<int, CourseSkill>, candidatesForStorage: list<array{course_skill_id: int, score: float}>}  $prepared
     *
     * @throws GeminiException
     * @throws \JsonException
     */
    private function resolve(array $response, Skill $skill, array $prepared): MatchJudgeResult
    {
        $rawText = $this->extractCandidateText($response);
        if ($rawText === null) {
            throw new GeminiException('Respons AI tidak mengandung teks.');
        }

        $decoded = json_decode($rawText, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new GeminiException('Format JSON hasil penilaian tidak valid.');
        }

        $classification = MatchClassification::tryFrom((string) ($decoded['classification'] ?? ''))
            ?? MatchClassification::Missing;
        $matchedId = isset($decoded['matched_course_skill_id']) && is_numeric($decoded['matched_course_skill_id'])
            ? (int) $decoded['matched_course_skill_id']
            : null;
        $reason = (string) ($decoded['reason'] ?? 'Penilaian berbasis AI.');
        $evidence = isset($decoded['evidence']) && is_string($decoded['evidence']) && trim($decoded['evidence']) !== ''
            ? trim($decoded['evidence'])
            : null;

        $rawResult = new MatchJudgeResult(
            classification: $classification,
            matchedCourseSkillId: $matchedId,
            similarityScore: null,
            reason: $reason,
            evidence: $evidence,
            judgedBy: JudgedBy::Llm,
            candidates: $prepared['candidatesForStorage'],
        );

        return $this->guardrails->apply(
            raw: $rawResult,
            skill: $skill,
            candidateMap: $prepared['candidateMap'],
            bestScore: $prepared['bestScore'],
            simFloor: $prepared['simFloor'],
        );
    }

    /**
     * Graceful fallback to missing when the LLM call fails.
     *
     * @param  array{rule: ?MatchJudgeResult, prompt: string, bestScore: float, simFloor: float, candidateMap: array<int, CourseSkill>, candidatesForStorage: list<array{course_skill_id: int, score: float}>}  $prepared
     */
    private function failed(array $prepared, Throwable $e): MatchJudgeResult
    {
        $bestScore = $prepared['bestScore'];

        return new MatchJudgeResult(
            classification: MatchClassification::Missing,
            matchedCourseSkillId: null,
            similarityScore: $bestScore > 0 ? $bestScore : null,
            reason: 'Gagal melakukan penilaian kesesuaian otomatis: '.$e->getMessage(),
            evidence: null,
            judgedBy: JudgedBy::Rule,
            candidates: $prepared['candidatesForStorage'],
        );
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
