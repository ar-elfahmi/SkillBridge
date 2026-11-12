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
use App\Modules\Ai\GeminiClient;
use Throwable;

class MatchJudge
{
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
            return new MatchJudgeResult(
                classification: MatchClassification::Missing,
                matchedCourseSkillId: null,
                similarityScore: $bestCandidate !== null ? $bestScore : null,
                reason: 'Tidak ditemukan kompetensi RPS yang memenuhi ambang batas kemiripan semantik minimum.',
                evidence: null,
                judgedBy: JudgedBy::Rule,
                candidates: $candidatesForStorage,
            );
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

        $responseSchema = [
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

        try {
            $response = $this->gemini->generateContent(
                purpose: 'match_judge',
                prompt: $prompt,
                promptVersion: 'match-judge-v1',
                subject: $analysis,
                responseSchema: $responseSchema,
            );

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
                candidates: $candidatesForStorage,
            );

            return $this->guardrails->apply(
                raw: $rawResult,
                skill: $skill,
                candidateMap: $candidateMap,
                bestScore: $bestScore,
                simFloor: $simFloor,
            );
        } catch (Throwable $e) {
            // Graceful fallback to missing when LLM call fails
            return new MatchJudgeResult(
                classification: MatchClassification::Missing,
                matchedCourseSkillId: null,
                similarityScore: $bestScore > 0 ? $bestScore : null,
                reason: 'Gagal melakukan penilaian kesesuaian otomatis: '.$e->getMessage(),
                evidence: null,
                judgedBy: JudgedBy::Rule,
                candidates: $candidatesForStorage,
            );
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
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                return $part['text'];
            }
        }

        return null;
    }
}
