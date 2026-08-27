<?php

namespace App\Domain\Identity\Support;

use App\Domain\Family\Models\Family;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Identity\Models\ParentInvitation;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\Relationship;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Identity\Models\UserAccount;

final class FamilyPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forSchool(Family $family): array
    {
        $links = SchoolPersonLink::query()
            ->whereIn('person_id', FamilyMember::query()->where('family_id', $family->id)->whereNull('left_at')->pluck('person_id'))
            ->get()
            ->keyBy('person_id');

        $members = FamilyMember::query()
            ->where('family_id', $family->id)
            ->whereNull('left_at')
            ->orderBy('role_in_family')
            ->get();

        $personIds = $members->pluck('person_id');
        $people = Person::query()->whereIn('id', $personIds)->get()->keyBy('id');
        $hasAccount = UserAccount::query()->whereIn('person_id', $personIds)->pluck('person_id')->all();
        $pendingInvite = ParentInvitation::query()
            ->whereIn('person_id', $personIds)
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now())
            ->pluck('person_id')
            ->all();

        $relationships = Relationship::query()
            ->where('status', 'active')
            ->where(function ($query) use ($personIds): void {
                $query->whereIn('subject_person_id', $personIds)->orWhereIn('object_person_id', $personIds);
            })
            ->get();

        return [
            'id' => $family->id,
            'label' => $family->label,
            'primary_language' => $family->primary_language,
            'members' => $members->map(function (FamilyMember $member) use ($people, $links, $hasAccount, $pendingInvite, $relationships): array {
                $person = $people->get($member->person_id);
                if ($person === null) {
                    return [
                        'id' => $member->person_id,
                        'person_id' => $member->person_id,
                        'role_in_family' => $member->role_in_family,
                    ];
                }

                $link = $links->get($member->person_id);
                $payload = $link !== null
                    ? PersonPayload::forSchool($person, $link)
                    : [
                        'id' => $person->id,
                        'public_id' => $person->publicIdFormatted(),
                        'first_name' => $person->first_name,
                        'last_name' => $person->last_name,
                        'birth_date' => $person->birth_date?->toDateString(),
                        'kind' => $member->role_in_family === 'child' ? 'student' : 'parent',
                    ];

                $types = $relationships
                    ->filter(fn (Relationship $row): bool => $row->subject_person_id === $member->person_id || $row->object_person_id === $member->person_id)
                    ->map(fn (Relationship $row): string => $row->type instanceof \BackedEnum ? $row->type->value : (string) $row->type)
                    ->unique()
                    ->values()
                    ->all();

                $payload['person_id'] = $member->person_id;
                $payload['role_in_family'] = $member->role_in_family;
                $payload['relationship_types'] = $types;
                $payload['has_account'] = in_array($member->person_id, $hasAccount, true);
                $payload['invitation_pending'] = in_array($member->person_id, $pendingInvite, true);

                if ($member->role_in_family === 'adult') {
                    $payload['phone_e164'] = $person->phone_e164;
                    $payload['email'] = $person->email;
                }

                return $payload;
            })->values()->all(),
        ];
    }
}
