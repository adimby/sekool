<?php

namespace App\Domain\SchoolKit\Enums;

enum KitFulfillment: string
{
    case Partner = 'partner';
    case Self = 'self';

    public function label(): string
    {
        return match ($this) {
            self::Partner => 'Chez le partenaire',
            self::Self => 'Le parent fournit',
        };
    }
}
