<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Identity\PublicId\FanabePublicId;
use App\Domain\Platform\Support\PhoneNumber;

final class FindSimilarPersons
{
    /**
     * @param  array{first_name?: string, last_name?: string, phone?: ?string}  $civil
     * @param  list<string>  $exceptPersonIds
     * @return list<array<string, mixed>>
     */
    public function inSchool(string $schoolId, array $civil, array $exceptPersonIds = []): array
    {
        $first = trim((string) ($civil['first_name'] ?? ''));
        $last = trim((string) ($civil['last_name'] ?? ''));
        $phone = trim((string) ($civil['phone'] ?? ''));
        if ($phone !== '') {
            try {
                $phone = PhoneNumber::parse($phone)->e164();
            } catch (\InvalidArgumentException) {
                // keep the raw value so an exact match can still fire
            }
        }
        if ($first === '' && $last === '' && $phone === '') {
            return [];
        }

        $linkedIds = SchoolPersonLink::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->pluck('person_id');

        $query = Person::query()
            ->whereIn('id', $linkedIds)
            ->whereNull('merged_into_person_id');

        $except = array_values(array_filter($exceptPersonIds, fn (string $id): bool => $id !== ''));
        if ($except !== []) {
            $query->whereNotIn('id', $except);
        }

        $query->where(function ($builder) use ($first, $last, $phone): void {
            if ($last !== '') {
                $builder->orWhereRaw('last_name % ?', [$last]);
            }
            if ($first !== '' && $last !== '') {
                $builder->orWhere(function ($inner) use ($first, $last): void {
                    $inner->whereRaw('lower(last_name) = ?', [mb_strtolower($last)])
                        ->whereRaw('lower(first_name) = ?', [mb_strtolower($first)]);
                });
            }
            if ($phone !== '') {
                $builder->orWhere('phone_e164', $phone);
            }
        });

        return $query->orderBy('last_name')->limit(8)->get()->map(fn (Person $person): array => [
            'id' => $person->id,
            'public_id' => FanabePublicId::fromCanonical($person->public_id)->formatted(),
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'hint' => 'Possible doublon (nom proche). La création n’est pas bloquée.',
        ])->values()->all();
    }
}
