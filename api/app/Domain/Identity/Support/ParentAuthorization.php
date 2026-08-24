<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\Relationship;

final class ParentAuthorization
{
    public static function isLegalGuardianOf(string $adultPersonId, string $childPersonId): bool
    {
        return Relationship::query()
            ->where('subject_person_id', $adultPersonId)
            ->where('object_person_id', $childPersonId)
            ->whereIn('type', [RelationshipType::ParentOf, RelationshipType::GuardianOf])
            ->where('status', 'active')
            ->exists();
    }

    /**
     * @return list<string>
     */
    public static function authorizedChildIds(string $adultPersonId): array
    {
        return Relationship::query()
            ->where('subject_person_id', $adultPersonId)
            ->whereIn('type', [RelationshipType::ParentOf, RelationshipType::GuardianOf])
            ->where('status', 'active')
            ->pluck('object_person_id')
            ->all();
    }
}
