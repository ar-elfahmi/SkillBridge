<?php

declare(strict_types=1);

namespace App\Enums;

enum CompetencySourceType: string
{
    case Cpl = 'CPL';
    case Cpmk = 'CPMK';
    case SubCpmk = 'Sub-CPMK';
    case Material = 'material';
    case WeeklyPlan = 'weekly_plan';
    case Assignment = 'assignment';
    case User = 'user';
}
