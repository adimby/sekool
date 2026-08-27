<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Academic\Support\ClassroomPeople;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class SaveTimetableSlot
{
    /**
     * @param  array{
     *     weekday: int,
     *     starts_at: string,
     *     ends_at: string,
     *     subject: string,
     *     teacher_person_id?: string|null,
     *     room?: string|null
     * }  $data
     */
    public function execute(string $schoolId, string $classroomId, array $data, ?string $slotId = null): TimetableSlot
    {
        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        $weekday = (int) $data['weekday'];
        if ($weekday < 1 || $weekday > 6) {
            throw new DomainException('Le jour doit être entre lundi (1) et samedi (6).');
        }

        $starts = $this->normalizeTime($data['starts_at']);
        $ends = $this->normalizeTime($data['ends_at']);
        if ($ends <= $starts) {
            throw new DomainException('L’heure de fin doit être après l’heure de début.');
        }

        $teacherId = $data['teacher_person_id'] ?? null;
        if (is_string($teacherId) && $teacherId !== '') {
            ClassroomPeople::assertStaff($schoolId, $teacherId);
        } else {
            $teacherId = null;
        }

        $this->assertNoOverlap($classroomId, $weekday, $starts, $ends, $slotId);

        $attrs = [
            'weekday' => $weekday,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'subject' => trim($data['subject']),
            'teacher_person_id' => $teacherId,
            'room' => isset($data['room']) && trim((string) $data['room']) !== '' ? trim((string) $data['room']) : null,
        ];

        if ($slotId !== null) {
            $slot = TimetableSlot::query()->where('classroom_id', $classroomId)->find($slotId);
            if ($slot === null) {
                throw new DomainException('Créneau introuvable.', 404);
            }
            $slot->fill($attrs)->save();
        } else {
            $slot = TimetableSlot::query()->create([
                'school_id' => $schoolId,
                'classroom_id' => $classroomId,
                ...$attrs,
            ]);
        }

        Auditor::record('timetable.saved', 'timetable_slot', $slot->id, null, [
            'classroom_id' => $classroomId,
            'weekday' => $weekday,
        ]);

        return $slot->load('teacher');
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value.':00';
        }

        return $value;
    }

    private function assertNoOverlap(string $classroomId, int $weekday, string $starts, string $ends, ?string $exceptId): void
    {
        $query = TimetableSlot::query()
            ->where('classroom_id', $classroomId)
            ->where('weekday', $weekday)
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw new DomainException('Ce créneau chevauche un autre cours de la classe.');
        }
    }
}
