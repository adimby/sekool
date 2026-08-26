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

        $enrollment->classroom_id = $classroom->id;
        $enrollment->save();

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
