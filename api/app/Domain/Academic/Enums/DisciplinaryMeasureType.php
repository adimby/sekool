<?php

namespace App\Domain\Academic\Enums;

enum DisciplinaryMeasureType: string
{
    case Warning = 'warning';
    case Detention = 'detention';
    case Meeting = 'meeting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Warning => 'Avertissement',
            self::Detention => 'Retenue',
            self::Meeting => 'Convocation',
            self::Other => 'Autre',
        };
    }
}
