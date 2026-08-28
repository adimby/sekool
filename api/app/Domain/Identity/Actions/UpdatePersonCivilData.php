<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\BirthDatePrecision;
use App\Domain\Identity\Enums\Sex;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Support\PhoneNumber;
use InvalidArgumentException;

final class UpdatePersonCivilData
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(Person $person, array $input, bool $includeContacts): Person
    {
        $attributes = [];

        foreach (['first_name', 'last_name', 'birth_date', 'preferred_language'] as $field) {
            if (array_key_exists($field, $input) && $input[$field] !== null && $input[$field] !== '') {
                $attributes[$field] = $input[$field];
            }
        }

        if (array_key_exists('sex', $input) && is_string($input['sex'])) {
            $attributes['sex'] = Sex::tryFrom($input['sex'])?->value ?? $person->sex;
        }

        if (array_key_exists('birth_date_precision', $input) && is_string($input['birth_date_precision'])) {
            $attributes['birth_date_precision'] = BirthDatePrecision::tryFrom($input['birth_date_precision'])?->value
                ?? $person->birth_date_precision;
        }

        if ($includeContacts) {
            if (array_key_exists('phone', $input)) {
                try {
                    $attributes['phone_e164'] = $input['phone'] === null || $input['phone'] === ''
                        ? null
                        : PhoneNumber::parse((string) $input['phone'])->e164();
                } catch (InvalidArgumentException) {
                    throw new DomainException('Numéro de téléphone invalide.');
                }
            }
            if (array_key_exists('email', $input)) {
                $attributes['email'] = $input['email'] === null || $input['email'] === ''
                    ? null
                    : $input['email'];
            }
        }

        if ($attributes === []) {
            return $person;
        }

        $person->forceFill($attributes)->save();

        Auditor::record('person.updated', 'person', $person->id, $person->id, array_keys($attributes));

        return $person->refresh();
    }
}
