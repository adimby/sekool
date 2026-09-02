<?php

namespace App\Domain\Academic\Enums;

enum SchoolEventType: string
{
    case Meeting = 'meeting';
    case OpenDay = 'open_day';
    case Tournament = 'tournament';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Réunion',
            self::OpenDay => 'Portes ouvertes',
            self::Tournament => 'Tournoi',
            self::Other => 'Autre',
        };
    }
}
