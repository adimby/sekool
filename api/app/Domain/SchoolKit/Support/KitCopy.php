<?php

namespace App\Domain\SchoolKit\Support;

final class KitCopy
{
    public static function payAtSupplier(?string $supplierName): string
    {
        $name = trim((string) $supplierName);

        return $name === ''
            ? 'Payer chez le fournisseur. FANABE n’encaisse pas.'
            : 'Payer chez '.$name.'. FANABE n’encaisse pas.';
    }
}
