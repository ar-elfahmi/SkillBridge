<?php

declare(strict_types=1);

namespace App\Modules\Matching\Services;

use App\Enums\JudgedBy;
use App\Enums\MatchClassification;

readonly class MatchJudgeResult
{
    /**
     * @param  list<array{course_skill_id: int, score: float}>  $candidates
     */
    public function __construct(
        public MatchClassification $classification,
        public ?int $matchedCourseSkillId,
        public ?float $similarityScore,
        public string $reason,
        public ?string $evidence,
        public JudgedBy $judgedBy,
        public array $candidates = [],
    ) {}

    /**
     * Return a modified copy of this result.
     */
    public function with(
        ?MatchClassification $classification = null,
        ?int $matchedCourseSkillId = null,
        ?float $similarityScore = null,
        ?string $reason = null,
        ?string $evidence = null,
        ?JudgedBy $judgedBy = null,
        ?array $candidates = null,
    ): self {
        return new self(
            classification: $classification ?? $this->classification,
            matchedCourseSkillId: $matchedCourseSkillId !== null ? $matchedCourseSkillId : $this->matchedCourseSkillId,
            similarityScore: $similarityScore ?? $this->similarityScore,
            reason: $reason ?? $this->reason,
            evidence: $evidence !== null ? $evidence : $this->evidence,
            judgedBy: $judgedBy ?? $this->judgedBy,
            candidates: $candidates ?? $this->candidates,
        );
    }
}
