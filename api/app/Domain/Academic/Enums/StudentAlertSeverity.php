<?php

namespace App\Domain\Academic\Enums;

enum StudentAlertSeverity: string
{
    case Info = 'info';
    case Attention = 'attention';
    case Priority = 'priority';
}
