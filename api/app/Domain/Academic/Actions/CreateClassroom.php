<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\SchoolYear;

final class CreateClassroom
{
    public function execute(
        string $schoolId,
        string $schoolYearId,
        string $gradeLevelId,
        string $name,
        ?int $capacity = null,
        ?string $mainTeacherPersonId = null,
    ): Classroom {
        $year = SchoolYear::query()->find($schoolYearId);
        if ($year === null || (string) $year->school_id !== $schoolId) {
            throw new DomainException('Année scolaire introuvable.', 404);
        }

        $grade = GradeLevel::query()->find($gradeLevelId);
        if ($grade === null || (string) $grade->school_id !== $schoolId) {
            throw new DomainException('Niveau introuvable.', 404);
        }

        $classroom = Classroom::query()->create([
            'school_id' => $schoolId,
            'school_year_id' => $schoolYearId,
            'grade_level_id' => $gradeLevelId,
            'name' => $name,
            'capacity' => $capacity,
            'main_teacher_person_id' => $mainTeacherPersonId,
        ]);

        Auditor::record('classroom.created', 'classroom', $classroom->id);

        return $classroom;
    }
}
