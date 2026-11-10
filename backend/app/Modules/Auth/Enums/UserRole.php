<?php

declare(strict_types=1);

namespace App\Modules\Auth\Enums;

enum UserRole: string
{
    case Student = 'student';
    case StudyProgram = 'study_program';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
