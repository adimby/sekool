<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\GradeEntry;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Support\ClassroomCycle;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class RecordGrade
{
    /**
     * @param  array{
     *     subject_id: string,
     *     enrollment_id: string,
     *     academic_term_id?: string|null,
     *     value: float|int|string,
     *     max_value?: float|int|string,
     *     coefficient?: float|int|string,
     *     assessed_on: string
     * }  $input
     */
    public function execute(string $schoolId, string $actorPersonId, array $input): GradeEntry
    {
        $enrollment = Enrollment::query()->with('classroom.gradeLevel')->find($input['enrollment_id']);
        if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
            throw new DomainException('Inscription introuvable.', 404);
        }
        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw new DomainException('Une note ne s’enregistre que pour une inscription active.');
        }
        if ($enrollment->classroom !== null && ClassroomCycle::of($enrollment->classroom) === GradeStage::Preschool) {
            throw new DomainException('Les notes ne s’appliquent pas à la maternelle.');
        }

        $subject = Subject::query()->find($input['subject_id']);
        if ($subject === null || (string) $subject->school_id !== $schoolId) {
            throw new DomainException('Matière introuvable.', 404);
        }

        $entry = GradeEntry::query()->create([
            'school_id' => $schoolId,
            'subject_id' => $subject->id,
            'enrollment_id' => $enrollment->id,
            'academic_term_id' => $input['academic_term_id'] ?? null,
            'recorded_by_person_id' => $actorPersonId,
            'value' => $input['value'],
            'max_value' => $input['max_value'] ?? 20,
            'coefficient' => $input['coefficient'] ?? 1,
            'assessed_on' => $input['assessed_on'],
        ]);

        Auditor::record('grade.recorded', 'grade_entry', $entry->id, $enrollment->person_id);

        return $entry->load('subject');
    }
}
