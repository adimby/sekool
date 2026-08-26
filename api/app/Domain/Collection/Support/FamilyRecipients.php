<?php

namespace App\Domain\Collection\Support;

use App\Domain\Family\Models\FamilyMember;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\Relationship;

final class FamilyRecipients
{
    /**
     * @return list<Person>
     */
    public static function adultsForStudent(string $studentPersonId): array
    {
        $ids = [];

        $membership = FamilyMember::query()
            ->where('person_id', $studentPersonId)
            ->whereNull('left_at')
            ->first();

        if ($membership !== null) {
            $ids = FamilyMember::query()
                ->where('family_id', $membership->family_id)
                ->where('role_in_family', 'adult')
                ->whereNull('left_at')
                ->pluck('person_id')
                ->all();
        }

        $related = Relationship::query()
            ->where('object_person_id', $studentPersonId)
            ->whereIn('type', [RelationshipType::ParentOf, RelationshipType::GuardianOf])
            ->where('status', 'active')
            ->pluck('subject_person_id')
            ->all();

        $ids = array_values(array_unique([...$ids, ...$related]));

        if ($ids === []) {
            return [];
        }

        return Person::query()->whereIn('id', $ids)->get()->all();
    }

    public static function familyIdForStudent(string $studentPersonId): ?string
    {
        return FamilyMember::query()
            ->where('person_id', $studentPersonId)
            ->whereNull('left_at')
            ->value('family_id');
    }
}
