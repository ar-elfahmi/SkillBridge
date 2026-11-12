<?php

declare(strict_types=1);

namespace App\Modules\Matching\Services;

use App\Enums\MatchClassification;
use App\Enums\VerificationStatus;
use App\Models\CourseSkill;
use App\Models\Skill;
use App\Modules\Competency\Services\EvidenceVerifier;

class ClassificationGuardrails
{
    public function __construct(
        private readonly EvidenceVerifier $evidenceVerifier,
    ) {}

    /**
     * Apply deterministic verification rules to an AI or rule-based match result.
     *
     * @param  array<int, CourseSkill>  $candidateMap  Map of course_skill_id => CourseSkill
     */
    public function apply(
        MatchJudgeResult $raw,
        Skill $skill,
        array $candidateMap,
        float $bestScore,
        float $simFloor,
    ): MatchJudgeResult {
        $classification = $raw->classification;
        $matchedId = $raw->matchedCourseSkillId;
        $evidence = $raw->evidence;
        $reason = $raw->reason;

        // Guardrail 1: Score < sim_floor must strictly be missing
        if ($bestScore < $simFloor) {
            return new MatchJudgeResult(
                classification: MatchClassification::Missing,
                matchedCourseSkillId: null,
                similarityScore: $bestScore > 0 ? $bestScore : null,
                reason: $raw->reason !== '' ? $raw->reason : 'Skor kemiripan tertinggi berada di bawah ambang batas minimum.',
                evidence: null,
                judgedBy: $raw->judgedBy,
                candidates: $raw->candidates,
            );
        }

        // Guardrail 2: Matched ID must exist in top-K candidate list
        if ($classification !== MatchClassification::Missing) {
            if ($matchedId === null || ! isset($candidateMap[$matchedId])) {
                $classification = MatchClassification::Missing;
                $matchedId = null;
                $evidence = null;
            }
        } else {
            $matchedId = null;
            $evidence = null;
        }

        if ($matchedId !== null && isset($candidateMap[$matchedId])) {
            $candidate = $candidateMap[$matchedId];

            // Guardrail 3: Evidence verification against candidate competency & evidence text
            if ($evidence !== null && trim($evidence) !== '') {
                $candidateCombined = trim(($candidate->competency ?? '').' '.($candidate->evidence ?? ''));
                if (! $this->evidenceVerifier->verifyInText($evidence, $candidateCombined)) {
                    // Invalid evidence citation: cap covered to partial, and discard evidence
                    if ($classification === MatchClassification::Covered) {
                        $classification = MatchClassification::Partial;
                    }
                    $evidence = null;
                }
            } elseif ($classification === MatchClassification::Covered) {
                // Covered requires strong evidence; without evidence citation, cap to partial
                $classification = MatchClassification::Partial;
            }

            // Guardrail 4: Technology-specific skill (language, framework, tool) requires technology name
            if ($skill->category?->isTechnologySpecific() === true && $classification === MatchClassification::Covered) {
                $techNames = array_filter(array_merge([$skill->canonical_name], $skill->aliases ?? []));
                $normCompetency = mb_strtolower($this->evidenceVerifier->normalizeText($candidate->competency ?? ''));
                $normCandidateEvidence = mb_strtolower($this->evidenceVerifier->normalizeText($candidate->evidence ?? ''));

                $hasTechName = false;
                foreach ($techNames as $techName) {
                    $needle = mb_strtolower($this->evidenceVerifier->normalizeText((string) $techName));
                    if ($needle !== '' && (str_contains($normCompetency, $needle) || str_contains($normCandidateEvidence, $needle))) {
                        $hasTechName = true;
                        break;
                    }
                }

                if (! $hasTechName) {
                    $classification = MatchClassification::Partial;
                }
            }

            // Guardrail 5: Unverified competency cannot produce covered; max partial
            if ($candidate->verification_status === VerificationStatus::Unverified) {
                if ($classification === MatchClassification::Covered) {
                    $classification = MatchClassification::Partial;
                }
            }
        }

        // Similarity score is the score of the matched candidate, or the best candidate score
        $similarityScore = $raw->similarityScore;
        if ($similarityScore === null && $matchedId !== null) {
            foreach ($raw->candidates as $candItem) {
                if ($candItem['course_skill_id'] === $matchedId) {
                    $similarityScore = $candItem['score'];
                    break;
                }
            }
        }
        $similarityScore ??= ($bestScore > 0 ? $bestScore : null);

        return new MatchJudgeResult(
            classification: $classification,
            matchedCourseSkillId: $matchedId,
            similarityScore: $similarityScore,
            reason: $reason,
            evidence: $evidence,
            judgedBy: $raw->judgedBy,
            candidates: $raw->candidates,
        );
    }
}
