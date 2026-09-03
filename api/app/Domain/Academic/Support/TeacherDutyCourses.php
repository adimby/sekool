<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\School\Support\SchoolGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Courses a teacher may start on a given date (own slot, substitute, or day roll).
 */
final class TeacherDutyCourses
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forDate(Request $request, string $date): array
    {
        $personId = $request->user()?->person_id;
        if (! is_string($personId) || $personId === '') {
            return [];
        }

        $weekday = TimetableDuty::weekdayOf($date);
        $slots = TimetableSlot::query()
            ->with('classroom.gradeLevel')
            ->where('weekday', $weekday)
            ->where(function (Builder $query) use ($personId, $date): void {
                $query->where('teacher_person_id', $personId)
                    ->orWhereHas(
                        'substitutions',
                        fn (Builder $subs) => $subs->whereDate('on_date', $date)
                            ->where('substitute_person_id', $personId),
                    );
            })
            ->orderBy('starts_at')
            ->orderBy('classroom_id')
            ->get();

        $courses = [];
        foreach ($slots as $slot) {
            $row = self::period($slot, $date);
            if ($row['cancelled'] && $slot->teacher_person_id !== $personId) {
                continue;
            }
            if (! $row['cancelled'] && $row['teacher_person_id'] !== $personId) {
                continue;
            }
            $courses[] = $row;
        }

        foreach (SchoolGate::dayAttendanceClassrooms($request)->with('gradeLevel')->orderBy('name')->get() as $classroom) {
            if (! SchoolGate::canTakeAttendance($request, $classroom, null, $date)) {
                continue;
            }
            $courses[] = self::fullDay($classroom);
        }

        return $courses;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forClassroom(Classroom $classroom, string $date, Request $request): array
    {
        if (SchoolGate::isDirection($request)) {
            return TimetableSlot::query()
                ->where('classroom_id', $classroom->id)
                ->where('weekday', TimetableDuty::weekdayOf($date))
                ->orderBy('starts_at')
                ->get()
                ->map(fn (TimetableSlot $slot): array => self::period($slot, $date))
                ->values()
                ->all();
        }

        $personId = $request->user()?->person_id;

        return array_values(array_filter(
            self::forDate($request, $date),
            function (array $row) use ($classroom, $personId): bool {
                if ((string) $row['classroom_id'] !== (string) $classroom->id) {
                    return false;
                }
                if ($row['kind'] === 'full_day') {
                    return true;
                }

                return $row['teacher_person_id'] === $personId || $row['cancelled'] === true;
            },
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function period(TimetableSlot $slot, string $date): array
    {
        $slot->loadMissing('classroom');
        $effective = TimetableDuty::effectiveTeacherPersonId($slot, $date);
        $cancelled = $effective === null && TimetableDuty::isCancelled($slot, $date);

        return [
            'kind' => 'period',
            'id' => $slot->id,
            'classroom_id' => $slot->classroom_id,
            'classroom_name' => $slot->classroom?->name,
            'timetable_slot_id' => $slot->id,
            'subject' => $slot->subject,
            'starts_at' => substr((string) $slot->starts_at, 0, 5),
            'ends_at' => substr((string) $slot->ends_at, 0, 5),
            'room' => $slot->room,
            'teacher_person_id' => $effective,
            'scheduled_teacher_person_id' => $slot->teacher_person_id,
            'cancelled' => $cancelled,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fullDay(Classroom $classroom): array
    {
        return [
            'kind' => 'full_day',
            'id' => $classroom->id,
            'classroom_id' => $classroom->id,
            'classroom_name' => $classroom->name,
            'timetable_slot_id' => null,
            'subject' => 'Appel du jour',
            'starts_at' => null,
            'ends_at' => null,
            'room' => null,
            'teacher_person_id' => $classroom->main_teacher_person_id,
            'scheduled_teacher_person_id' => $classroom->main_teacher_person_id,
            'cancelled' => false,
        ];
    }
}
