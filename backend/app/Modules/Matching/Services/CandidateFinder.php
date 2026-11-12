<?php

declare(strict_types=1);

namespace App\Modules\Matching\Services;

use App\Models\CourseSkill;
use App\Support\Cosine;

class CandidateFinder
{
    /**
     * Find top-K course skill candidates ranked by cosine similarity to the target embedding.
     *
     * @param  array<int, float|int>  $targetEmbedding
     * @param  iterable<CourseSkill>  $candidates
     * @return list<array{course_skill: CourseSkill, course_skill_id: int, score: float}>
     */
    public function findTopK(array $targetEmbedding, iterable $candidates, ?int $topK = null): array
    {
        if ($topK === null) {
            $topK = function_exists('config') && app()->has('config')
                ? (int) config('skillbridge.matching.top_k', 3)
                : 3;
        }

        if ($topK <= 0 || empty($targetEmbedding)) {
            return [];
        }

        $scored = [];

        foreach ($candidates as $candidate) {
            $embedding = $candidate->embedding;
            if (! is_array($embedding) || empty($embedding)) {
                continue;
            }

            // Cosine::similarity handles dimension mismatch, zero vectors, and [-1, 1] clamping.
            $score = Cosine::similarity($targetEmbedding, $embedding);

            $scored[] = [
                'course_skill' => $candidate,
                'course_skill_id' => (int) $candidate->id,
                'score' => $score,
            ];
        }

        if (empty($scored)) {
            return [];
        }

        usort($scored, function (array $a, array $b): int {
            // Sort by score descending
            if (abs($a['score'] - $b['score']) > 1e-9) {
                return $b['score'] <=> $a['score'];
            }

            // Tie-breaker: sort by course_skill_id ascending for deterministic ordering
            return $a['course_skill_id'] <=> $b['course_skill_id'];
        });

        return array_slice($scored, 0, $topK);
    }
}
