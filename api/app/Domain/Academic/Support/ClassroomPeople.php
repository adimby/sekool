<?php

namespace App\Domain\Academic\Support;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\SchoolRoleAssignment;

final class ClassroomPeople
{
    public static function assertStaff(string $schoolId, string $personId): Person
    {
        $person = Person::query()->find($personId);
        if ($person === null) {
            throw new DomainException('Personne introuvable.', 404);
        }

        $hasRole = SchoolRoleAssignment::query()
            ->where('person_id', $personId)
            ->whereNull('revoked_at')
            ->whereIn('role', [
                SchoolRole::Teacher,
                SchoolRole::Staff,
                SchoolRole::Principal,
                SchoolRole::Admin,
            ])
            ->exists();

        if (! $hasRole) {
            throw new DomainException('Cette personne n’a pas de rôle enseignant ou personnel dans l’établissement.');
        }

        return $person;
    }

    public static function assertStudentInClass(string $classroomId, string $personId): void
    {
        $inClass = Enrollment::query()
            ->where('classroom_id', $classroomId)
            ->where('person_id', $personId)
            ->where('status', EnrollmentStatus::Active)
            ->exists();

        if (! $inClass) {
            throw new DomainException('Le délégué doit être un élève de cette classe.');
        }
    }
}
