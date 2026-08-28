<?php

namespace App\Domain\Academic\Enums;

enum StudentAlertStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
