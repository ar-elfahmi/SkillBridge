<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Cosine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CosineTest extends TestCase
{
    public function test_identical_vectors_have_similarity_one(): void
    {
        $a = [1.0, 2.0, 3.0, 4.0];
        $this->assertEqualsWithDelta(1.0, Cosine::similarity($a, $a), 1e-6);
    }

    public function test_parallel_vectors_have_similarity_one(): void
    {
        $a = [1.0, 2.0, 3.0];
        $b = [2.0, 4.0, 6.0];
        $this->assertEqualsWithDelta(1.0, Cosine::similarity($a, $b), 1e-6);
    }

    public function test_orthogonal_vectors_have_similarity_zero(): void
    {
        $a = [1.0, 0.0, 0.0];
        $b = [0.0, 1.0, 0.0];
        $this->assertEqualsWithDelta(0.0, Cosine::similarity($a, $b), 1e-6);
    }

    public function test_opposite_vectors_have_similarity_minus_one(): void
    {
        $a = [1.0, -2.0, 3.0];
        $b = [-1.0, 2.0, -3.0];
        $this->assertEqualsWithDelta(-1.0, Cosine::similarity($a, $b), 1e-6);
    }

    public function test_known_angle_numerical_accuracy(): void
    {
        // 45 degrees angle: cos(45 deg) = sqrt(2)/2 ≈ 0.70710678
        $a = [1.0, 0.0];
        $b = [1.0, 1.0];
        $this->assertEqualsWithDelta(sqrt(2) / 2, Cosine::similarity($a, $b), 1e-6);

        // 60 degrees angle: cos(60 deg) = 0.5
        $c = [1.0, 0.0];
        $d = [1.0, sqrt(3)];
        $this->assertEqualsWithDelta(0.5, Cosine::similarity($c, $d), 1e-6);
    }

    public function test_zero_vector_returns_zero_similarity(): void
    {
        $a = [0.0, 0.0, 0.0];
        $b = [1.0, 2.0, 3.0];
        $this->assertSame(0.0, Cosine::similarity($a, $b));
        $this->assertSame(0.0, Cosine::similarity($b, $a));
    }

    public function test_dimension_mismatch_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cosine::similarity([1.0, 2.0], [1.0, 2.0, 3.0]);
    }

    public function test_empty_vector_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cosine::similarity([], []);
    }

    public function test_l2_normalize_produces_unit_length_vector(): void
    {
        $vector = [3.0, 4.0]; // Norm is 5.0
        $normalized = Cosine::l2Normalize($vector);

        $this->assertCount(2, $normalized);
        $this->assertEqualsWithDelta(0.6, $normalized[0], 1e-6);
        $this->assertEqualsWithDelta(0.8, $normalized[1], 1e-6);

        // Sum of squares of unit vector is 1.0
        $normSq = ($normalized[0] ** 2) + ($normalized[1] ** 2);
        $this->assertEqualsWithDelta(1.0, $normSq, 1e-6);
    }

    public function test_l2_normalize_handles_zero_vector(): void
    {
        $zero = [0.0, 0.0, 0.0];
        $normalized = Cosine::l2Normalize($zero);

        $this->assertSame([0.0, 0.0, 0.0], $normalized);
    }

    public function test_dot_product_calculation(): void
    {
        $a = [1.0, 2.0, 3.0];
        $b = [4.0, -5.0, 6.0];
        // 1*4 + 2*(-5) + 3*6 = 4 - 10 + 18 = 12
        $this->assertEqualsWithDelta(12.0, Cosine::dotProduct($a, $b), 1e-6);
    }
}
