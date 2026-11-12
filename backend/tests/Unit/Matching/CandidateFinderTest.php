<?php

declare(strict_types=1);

namespace Tests\Unit\Matching;

use App\Models\CourseSkill;
use App\Modules\Matching\Services\CandidateFinder;
use Tests\TestCase;

class CandidateFinderTest extends TestCase
{
    private CandidateFinder $finder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finder = new CandidateFinder;
    }

    public function test_ranks_candidates_by_cosine_similarity_descending(): void
    {
        $target = [1.0, 0.0, 0.0];

        $cs1 = new CourseSkill(['embedding' => [0.0, 1.0, 0.0]]); // orthogonal, score = 0.0
        $cs1->id = 101;

        $cs2 = new CourseSkill(['embedding' => [1.0, 0.0, 0.0]]); // parallel, score = 1.0
        $cs2->id = 102;

        $cs3 = new CourseSkill(['embedding' => [0.70710678, 0.70710678, 0.0]]); // 45 deg, score ~ 0.707
        $cs3->id = 103;

        $results = $this->finder->findTopK($target, [$cs1, $cs2, $cs3], topK: 3);

        $this->assertCount(3, $results);
        $this->assertSame(102, $results[0]['course_skill_id']);
        $this->assertEqualsWithDelta(1.0, $results[0]['score'], 1e-4);

        $this->assertSame(103, $results[1]['course_skill_id']);
        $this->assertEqualsWithDelta(0.7071, $results[1]['score'], 1e-3);

        $this->assertSame(101, $results[2]['course_skill_id']);
        $this->assertEqualsWithDelta(0.0, $results[2]['score'], 1e-4);
    }

    public function test_limits_to_requested_top_k(): void
    {
        $target = [1.0, 0.0];

        $candidates = [];
        for ($i = 1; $i <= 5; $i++) {
            $cs = new CourseSkill(['embedding' => [$i * 0.2, 1.0]]);
            $cs->id = $i;
            $candidates[] = $cs;
        }

        $results = $this->finder->findTopK($target, $candidates, topK: 2);

        $this->assertCount(2, $results);
    }

    public function test_ignores_candidates_with_null_or_empty_embedding(): void
    {
        $target = [1.0, 0.0];

        $cs1 = new CourseSkill(['embedding' => null]);
        $cs1->id = 1;

        $cs2 = new CourseSkill(['embedding' => []]);
        $cs2->id = 2;

        $cs3 = new CourseSkill(['embedding' => [1.0, 0.0]]);
        $cs3->id = 3;

        $results = $this->finder->findTopK($target, [$cs1, $cs2, $cs3], topK: 3);

        $this->assertCount(1, $results);
        $this->assertSame(3, $results[0]['course_skill_id']);
    }

    public function test_returns_empty_when_target_is_empty(): void
    {
        $cs = new CourseSkill(['embedding' => [1.0, 0.0]]);
        $cs->id = 1;

        $this->assertSame([], $this->finder->findTopK([], [$cs]));
    }

    public function test_returns_empty_when_top_k_is_zero_or_negative(): void
    {
        $target = [1.0, 0.0];
        $cs = new CourseSkill(['embedding' => [1.0, 0.0]]);
        $cs->id = 1;

        $this->assertSame([], $this->finder->findTopK($target, [$cs], topK: 0));
        $this->assertSame([], $this->finder->findTopK($target, [$cs], topK: -1));
    }

    public function test_tie_breaker_sorts_by_course_skill_id_ascending(): void
    {
        $target = [1.0, 0.0];

        // Both have exact same embedding and score (1.0)
        $csHighId = new CourseSkill(['embedding' => [1.0, 0.0]]);
        $csHighId->id = 200;

        $csLowId = new CourseSkill(['embedding' => [1.0, 0.0]]);
        $csLowId->id = 50;

        $results = $this->finder->findTopK($target, [$csHighId, $csLowId], topK: 2);

        $this->assertCount(2, $results);
        $this->assertSame(50, $results[0]['course_skill_id']);
        $this->assertSame(200, $results[1]['course_skill_id']);
    }
}
