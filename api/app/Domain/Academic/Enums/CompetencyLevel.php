<?php

namespace App\Domain\Academic\Enums;

enum CompetencyLevel: string
{
    case NotYet = 'not_yet';
    case InProgress = 'in_progress';
    case Acquired = 'acquired';

    public function label(): string
    {
        return match ($this) {
            self::NotYet => 'Pas encore',
            self::InProgress => 'En cours',
            self::Acquired => 'Acquis',
        };
    }
}
