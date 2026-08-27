<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\ClassroomTeacher;
use App\Domain\Academic\Support\ClassroomPeople;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class AddClassroomTeacher
{
    public function execute(string $schoolId, string $classroomId, string $personId, ?string $subject = null): ClassroomTeacher
    {
        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        ClassroomPeople::assertStaff($schoolId, $personId);

        $existing = ClassroomTeacher::query()
            ->where('classroom_id', $classroomId)
            ->where('person_id', $personId)
            ->first();

        if ($existing !== null) {
            if ($subject !== null && $subject !== '') {
                $existing->subject = $subject;
                $existing->save();
            }

            return $existing->load('person');
        }

        $row = ClassroomTeacher::query()->create([
            'school_id' => $schoolId,
            'classroom_id' => $classroomId,
            'person_id' => $personId,
            'subject' => $subject !== null && $subject !== '' ? $subject : null,
        ]);

        Auditor::record('classroom.teacher_added', 'classroom', $classroomId, $personId, [
            'subject' => $row->subject,
        ]);

        return $row->load('person');
    }
}
