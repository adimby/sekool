<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\ClassroomTeacher;
use App\Domain\Academic\Support\ClassroomCycle;
use App\Domain\Academic\Support\ClassroomPeople;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateClassroom
{
    /**
     * @param  array{
     *     name?: string,
     *     capacity?: int|null,
     *     series?: string|null,
     *     main_teacher_person_id?: string|null,
     *     delegate_person_id?: string|null,
     *     vice_delegate_person_id?: string|null
     * }  $data
     */
    public function execute(string $schoolId, string $classroomId, array $data): Classroom
    {
        return DB::transaction(function () use ($schoolId, $classroomId, $data): Classroom {
            $classroom = Classroom::query()->lockForUpdate()->find($classroomId);
            if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
                throw new DomainException('Classe introuvable.', 404);
            }

            $stage = ClassroomCycle::of($classroom);
            $settingDelegate = array_key_exists('delegate_person_id', $data) && filled($data['delegate_person_id']);
            $settingVice = array_key_exists('vice_delegate_person_id', $data) && filled($data['vice_delegate_person_id']);
            if (! $stage->allowsDelegate() && ($settingDelegate || $settingVice)) {
                throw new DomainException('La maternelle n\'a pas de délégué de classe.');
            }

            if (isset($data['name']) && trim($data['name']) !== '') {
                $classroom->name = trim($data['name']);
            }

            if (array_key_exists('capacity', $data)) {
                $capacity = $data['capacity'];
                $classroom->capacity = $capacity === null || $capacity === '' ? null : (int) $capacity;
            }

            if (array_key_exists('series', $data)) {
                $series = $data['series'];
                $classroom->series = $series === null || trim((string) $series) === ''
                    ? null
                    : mb_substr(trim((string) $series), 0, 32);
            }

            if (array_key_exists('main_teacher_person_id', $data)) {
                $teacherId = $data['main_teacher_person_id'];
                if ($teacherId === null || $teacherId === '') {
                    $classroom->main_teacher_person_id = null;
                } else {
                    ClassroomPeople::assertStaff($schoolId, $teacherId);
                    $classroom->main_teacher_person_id = $teacherId;
                    ClassroomTeacher::query()->firstOrCreate(
                        [
                            'school_id' => $schoolId,
                            'classroom_id' => $classroom->id,
                            'person_id' => $teacherId,
                        ],
                        ['subject' => null],
                    );
                }
            }

            $delegateId = array_key_exists('delegate_person_id', $data)
                ? ($data['delegate_person_id'] ?: null)
                : $classroom->delegate_person_id;
            $viceId = array_key_exists('vice_delegate_person_id', $data)
                ? ($data['vice_delegate_person_id'] ?: null)
                : $classroom->vice_delegate_person_id;

            if ($delegateId !== null) {
                ClassroomPeople::assertStudentInClass($classroom->id, $delegateId);
            }
            if ($viceId !== null) {
                ClassroomPeople::assertStudentInClass($classroom->id, $viceId);
            }
            if ($delegateId !== null && $viceId !== null && $delegateId === $viceId) {
                throw new DomainException('Le délégué et le vice-délégué doivent être deux élèves distincts.');
            }

            $classroom->delegate_person_id = $delegateId;
            $classroom->vice_delegate_person_id = $viceId;
            $classroom->save();

            Auditor::record('classroom.updated', 'classroom', $classroom->id);

            return $classroom->fresh([
                'gradeLevel',
                'mainTeacher',
                'delegate',
                'viceDelegate',
            ]) ?? $classroom;
        });
    }
}
