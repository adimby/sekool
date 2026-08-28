<?php

namespace App\Domain\Finance\Enums;

enum ExpenseCategory: string
{
    case Supplies = 'supplies';
    case Maintenance = 'maintenance';
    case Utilities = 'utilities';
    case Transport = 'transport';
    case Food = 'food';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Supplies => 'Fournitures',
            self::Maintenance => 'Entretien',
            self::Utilities => 'Charges',
            self::Transport => 'Transport',
            self::Food => 'Cantine / alimentation',
            self::Other => 'Autre',
        };
    }
}
