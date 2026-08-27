<?php

namespace App\Domain\Finance\Enums;

enum FeeScheduleStatus: string
{
    case Draft = 'draft';
    case PendingValidation = 'pending_validation';
    case Active = 'active';
}
