<?php

declare(strict_types=1);

namespace App\Modules\Auth\Enums;

/**
 * MVP has a single role. The column is kept so more roles can be added later
 * without a schema change (docs/PRD.md §8).
 */
enum UserRole: string
{
    case Student = 'student';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
