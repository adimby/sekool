<?php

namespace App\Domain\Finance\Enums;

enum FeeAdjustmentType: string
{
    case Amount = 'amount';
    case Percent = 'percent';
}
