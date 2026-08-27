<?php

namespace App\Domain\Academic\Enums;

enum ClassActivityType: string
{
    case ParentMeeting = 'parent_meeting';
    case Outing = 'outing';
    case Celebration = 'celebration';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ParentMeeting => 'Réunion parents',
            self::Outing => 'Sortie',
            self::Celebration => 'Fête',
            self::Other => 'Autre',
        };
    }
}
