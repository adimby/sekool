<?php

namespace App\Domain\Academic\Support;

use App\Domain\Identity\Models\Person;

final class PersonMini
{
    /**
     * @return array{id: string, first_name: string, last_name: string}|null
     */
    public static function make(?Person $person): ?array
    {
        if ($person === null) {
            return null;
        }

        return [
            'id' => $person->id,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
        ];
    }
}
