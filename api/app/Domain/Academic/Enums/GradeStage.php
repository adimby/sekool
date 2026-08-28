<?php

namespace App\Domain\Academic\Enums;

enum GradeStage: string
{
    case Preschool = 'preschool';
    case Primary = 'primary';
    case Middle = 'middle';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Preschool => 'Maternelle',
            self::Primary => 'Primaire',
            self::Middle => 'Collège',
            self::High => 'Lycée',
        };
    }
}
