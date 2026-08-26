<?php

namespace App\Domain\Academic\Enums;

enum GradeStage: string
{
    case Preschool = 'preschool';
    case Primary = 'primary';
    case Middle = 'middle';
    case High = 'high';
}
