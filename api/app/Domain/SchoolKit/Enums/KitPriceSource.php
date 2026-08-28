<?php

namespace App\Domain\SchoolKit\Enums;

enum KitPriceSource: string
{
    case Supplier = 'supplier';
    case Purchasing = 'purchasing';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Fournisseur partenaire',
            self::Purchasing => 'Service achat de l’école',
        };
    }
}
