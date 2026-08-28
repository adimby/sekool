<?php

namespace App\Domain\Academic\Enums;

enum StudentAlertCategory: string
{
    case GradesDecline = 'grades_decline';
    case AbsenceIncrease = 'absence_increase';
    case LatenessPattern = 'lateness_pattern';
    case HomeworkDecline = 'homework_decline';
    case Combined = 'combined';
}
