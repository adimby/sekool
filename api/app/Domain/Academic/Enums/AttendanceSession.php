<?php

namespace App\Domain\Academic\Enums;

enum AttendanceSession: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case FullDay = 'full_day';
    case Period = 'period';
}
