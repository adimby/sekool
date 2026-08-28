<?php

namespace App\Domain\Family\Support;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Family\Models\FamilyMember;

final class FamilyHasSchoolEnrollment
{
    public static function exists(string $familyId): bool
    {
        $studentIds = Enrollment::query()->pluck('person_id');

        return FamilyMember::query()
            ->where('family_id', $familyId)
            ->whereIn('person_id', $studentIds)
            ->whereNull('left_at')
            ->exists();
    }
}
