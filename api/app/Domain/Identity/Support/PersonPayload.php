<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Identity\PublicId\FanabePublicId;

final class PersonPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forSchool(Person $person, SchoolPersonLink $link): array
    {
        $payload = [
            'id' => $person->id,
            'public_id' => FanabePublicId::fromCanonical($person->public_id)->formatted(),
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'birth_date' => $person->birth_date?->toDateString(),
            'birth_date_precision' => $person->birth_date_precision?->value ?? $person->birth_date_precision,
            'sex' => $person->sex?->value ?? $person->sex,
            'preferred_language' => $person->preferred_language,
            'kind' => $link->kind instanceof SchoolPersonLinkKind ? $link->kind->value : $link->kind,
        ];

        if ($link->grants_contact_access) {
            $payload['phone_e164'] = $person->phone_e164;
            $payload['email'] = $person->email;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forParent(Person $person): array
    {
        return [
            'id' => $person->id,
            'public_id' => FanabePublicId::fromCanonical($person->public_id)->formatted(),
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'birth_date' => $person->birth_date?->toDateString(),
            'birth_date_precision' => $person->birth_date_precision?->value ?? $person->birth_date_precision,
            'sex' => $person->sex?->value ?? $person->sex,
            'preferred_language' => $person->preferred_language,
            'phone_e164' => $person->phone_e164,
            'email' => $person->email,
        ];
    }
}
