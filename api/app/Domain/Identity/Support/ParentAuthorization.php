<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\Relationship;

final class ParentAuthorization
{
    public static function isLegalGuardianOf(string $adultPersonId, string $childPersonId): bool
    {
        return self::hasActive($adultPersonId, $childPersonId, [
            RelationshipType::ParentOf,
            RelationshipType::GuardianOf,
        ]);
    }

    public static function canSeeFinance(string $adultPersonId, string $childPersonId): bool
    {
        return self::hasActive($adultPersonId, $childPersonId, [
            RelationshipType::ParentOf,
            RelationshipType::GuardianOf,
            RelationshipType::FinancialContactFor,
        ]);
    }

    public static function canSeeAttendance(string $adultPersonId, string $childPersonId): bool
    {
        return self::isLegalGuardianOf($adultPersonId, $childPersonId);
    }

    /**
     * @return list<string>
     */
    public static function authorizedChildIds(string $adultPersonId): array
    {
        return self::childIds($adultPersonId, [
            RelationshipType::ParentOf,
            RelationshipType::GuardianOf,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function accessibleChildIds(string $adultPersonId): array
    {
        return self::childIds($adultPersonId, [
            RelationshipType::ParentOf,
            RelationshipType::GuardianOf,
            RelationshipType::FinancialContactFor,
        ]);
    }

    /**
     * @param  list<RelationshipType>  $types
     */
    private static function hasActive(string $adultPersonId, string $childPersonId, array $types): bool
    {
        return Relationship::query()
            ->where('subject_person_id', $adultPersonId)
            ->where('object_person_id', $childPersonId)
            ->whereIn('type', $types)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * @param  list<RelationshipType>  $types
     * @return list<string>
     */
    private static function childIds(string $adultPersonId, array $types): array
    {
        return Relationship::query()
            ->where('subject_person_id', $adultPersonId)
            ->whereIn('type', $types)
            ->where('status', 'active')
            ->pluck('object_person_id')
            ->unique()
            ->values()
            ->all();
    }
}
