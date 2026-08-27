<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\ClassroomTeacher;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class RemoveClassroomTeacher
{
    public function execute(string $schoolId, string $classroomId, string $personId): void
    {
        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        if ($classroom->main_teacher_person_id === $personId) {
            throw new DomainException('Retirez d’abord le rôle de professeur titulaire.');
        }

        $row = ClassroomTeacher::query()
            ->where('classroom_id', $classroomId)
            ->where('person_id', $personId)
            ->first();

        if ($row === null) {
            throw new DomainException('Cet enseignant n’est pas rattaché à la classe.', 404);
        }

        $row->delete();

        Auditor::record('classroom.teacher_removed', 'classroom', $classroomId, $personId);
    }
}
