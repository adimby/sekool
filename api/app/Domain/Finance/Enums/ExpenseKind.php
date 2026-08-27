<?php

namespace App\Domain\Finance\Enums;

enum ExpenseKind: string
{
    case Purchase = 'purchase';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Achat',
            self::Expense => 'Dépense',
        };
    }
}
