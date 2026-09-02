<?php

namespace App\Domain\Academic\Enums;

enum DisciplinaryCaseStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'En cours',
            self::Done => 'Suivi fait',
            self::Cancelled => 'Annulée',
        };
    }
}
