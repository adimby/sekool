<?php

namespace App\Domain\SchoolKit\Enums;

enum KitPackTier: string
{
    case Eco = 'eco';
    case Standard = 'standard';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Eco => 'Éco',
            self::Standard => 'Standard',
            self::Premium => 'Premium',
        };
    }
}
