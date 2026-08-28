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
            self::Premium => 'Luxe',
        };
    }

    public static function parse(string $value): ?self
    {
        if ($value === 'luxe') {
            return self::Premium;
        }

        return self::tryFrom($value);
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Eco, self::Standard, self::Premium];
    }
}
