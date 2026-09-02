<?php

namespace App\Domain\Academic\Enums;

enum SchoolEventAudience: string
{
    case School = 'school';
    case Grade = 'grade';
    case Classroom = 'classroom';

    public function label(): string
    {
        return match ($this) {
            self::School => 'Toute l’école',
            self::Grade => 'Un niveau',
            self::Classroom => 'Une classe',
        };
    }
}
