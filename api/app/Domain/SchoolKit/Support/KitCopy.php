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

    public static function parentChoice(): string
    {
        return 'Commander une gamme chez le partenaire, ou fournir les articles vous-même. FANABE n’encaisse pas.';
    }
}
