<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Models\ExamSession;
use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Academic\Models\TimetableSubstitution;

final class EstablishmentTimetablePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function slot(TimetableSlot $slot): array
    {
        $slot->loadMissing(['classroom.gradeLevel', 'teacher']);

        return [
            'id' => $slot->id,
            'classroom_id' => $slot->classroom_id,
            'classroom' => $slot->classroom?->name,
            'weekday' => $slot->weekday,
            'starts_at' => substr((string) $slot->starts_at, 0, 5),
            'ends_at' => substr((string) $slot->ends_at, 0, 5),
            'subject' => $slot->subject,
            'room' => $slot->room,
            'teacher_person_id' => $slot->teacher_person_id,
            'teacher' => PersonMini::make($slot->teacher),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function substitution(TimetableSubstitution $row): array
    {
        $row->loadMissing(['substitute', 'slot']);

        return [
            'id' => $row->id,
            'timetable_slot_id' => $row->timetable_slot_id,
            'classroom_id' => $row->classroom_id,
            'on_date' => $row->on_date?->toDateString(),
            'subject' => $row->slot?->subject,
            'cancelled' => $row->substitute_person_id === null,
            'reason' => $row->reason,
            'substitute_person_id' => $row->substitute_person_id,
            'substitute' => PersonMini::make($row->substitute),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exam(ExamSession $row): array
    {
        $row->loadMissing('classroom');

        return [
            'id' => $row->id,
            'classroom_id' => $row->classroom_id,
            'classroom' => $row->classroom?->name,
            'title' => $row->title,
            'subject' => $row->subject,
            'held_on' => $row->held_on?->toDateString(),
            'starts_at' => substr((string) $row->starts_at, 0, 5),
            'ends_at' => substr((string) $row->ends_at, 0, 5),
            'room' => $row->room,
            'body' => $row->body,
        ];
    }
}
