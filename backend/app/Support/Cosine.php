<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Cosine
{
    /**
     * Compute cosine similarity between two numeric vectors.
     *
     * @param  list<float|int>  $a
     * @param  list<float|int>  $b
     *
     * @throws InvalidArgumentException
     */
    public static function similarity(array $a, array $b): float
    {
        $count = count($a);
        if ($count === 0 || $count !== count($b)) {
            throw new InvalidArgumentException('Vektor harus memiliki dimensi yang sama dan tidak boleh kosong.');
        }

        $dotProduct = 0.0;
        $normASq = 0.0;
        $normBSq = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $valA = (float) $a[$i];
            $valB = (float) $b[$i];

            $dotProduct += $valA * $valB;
            $normASq += $valA * $valA;
            $normBSq += $valB * $valB;
        }

        if ($normASq <= 0.0 || $normBSq <= 0.0) {
            return 0.0;
        }

        $sim = $dotProduct / (sqrt($normASq) * sqrt($normBSq));

        return max(-1.0, min(1.0, $sim));
    }

    /**
     * Normalize vector to unit length (L2 norm = 1.0).
     *
     * @param  list<float|int>  $vector
     * @return list<float>
     */
    public static function l2Normalize(array $vector): array
    {
        $normSq = 0.0;
        foreach ($vector as $val) {
            $v = (float) $val;
            $normSq += $v * $v;
        }

        if ($normSq <= 0.0) {
            return array_map(fn ($v): float => (float) $v, $vector);
        }

        $norm = sqrt($normSq);

        return array_map(fn ($v): float => (float) $v / $norm, $vector);
    }

    /**
     * Compute dot product between two vectors.
     *
     * @param  list<float|int>  $a
     * @param  list<float|int>  $b
     */
    public static function dotProduct(array $a, array $b): float
    {
        $count = count($a);
        if ($count === 0 || $count !== count($b)) {
            throw new InvalidArgumentException('Vektor harus memiliki dimensi yang sama dan tidak boleh kosong.');
        }

        $dotProduct = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += ((float) $a[$i]) * ((float) $b[$i]);
        }

        return $dotProduct;
    }
}
