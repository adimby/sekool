<?php

namespace App\Domain\Identity\Enums;

enum BirthDatePrecision: string
{
    case Exact = 'exact';
    case YearOnly = 'year_only';
    case Estimated = 'estimated';
}
