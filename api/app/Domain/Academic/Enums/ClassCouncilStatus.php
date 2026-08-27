<?php

namespace App\Domain\Academic\Enums;

enum ClassCouncilStatus: string
{
    case Scheduled = 'scheduled';
    case Held = 'held';
}
