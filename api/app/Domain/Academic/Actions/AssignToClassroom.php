<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class AssignToClassroom
{
    public function execute(string $schoolId, string $enrollmentId, string $classroomId): Enrollment
    {
        $enrollment = Enrollment::query()->find($enrollmentId);
        if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
            throw new DomainException('Inscription introuvable.', 404);
        }

        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw new DomainException('Seule une inscription active peut être affectée à une classe.');
        }

        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        if ((string) $classroom->school_year_id !== (string) $enrollment->school_year_id) {
            throw new DomainException('La classe n\'appartient pas à la même année scolaire.');
        }

        $previousClassroomId = $enrollment->classroom_id;

        $enrollment->classroom_id = $classroom->id;
        $enrollment->save();

        if ($previousClassroomId !== null && $previousClassroomId !== $classroom->id) {
            Classroom::query()
                ->whereKey($previousClassroomId)
                ->where('delegate_person_id', $enrollment->person_id)
                ->update(['delegate_person_id' => null]);
            Classroom::query()
                ->whereKey($previousClassroomId)
                ->where('vice_delegate_person_id', $enrollment->person_id)
                ->update(['vice_delegate_person_id' => null]);
        }

        Auditor::record(
            'enrollment.assigned_to_classroom',
            'enrollment',
            $enrollment->id,
            $enrollment->person_id,
            ['classroom_id' => $classroom->id],
        );

        return $enrollment->fresh(['person', 'classroom']) ?? $enrollment;
    }
}
