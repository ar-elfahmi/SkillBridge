<?php

declare(strict_types=1);

namespace App\Enums;

enum RecommendationPriority: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * Sort weight, higher first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }
}
