<?php

namespace App\Domain\SchoolKit\Enums;

enum KitOrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Fulfilled = 'fulfilled';
    case SelfSupplied = 'self_supplied';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Submitted => 'Commandée chez le partenaire',
            self::Confirmed => 'Confirmée',
            self::Fulfilled => 'Remise',
            self::SelfSupplied => 'Fournit lui-même',
            self::Cancelled => 'Annulée',
        };
    }
}
