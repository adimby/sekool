<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Academic\Models\TimetableSubstitution;
use Illuminate\Support\Carbon;

final class TimetableDuty
{
    public static function weekdayOf(string $date): int
    {
        return (int) Carbon::parse($date)->isoWeekday();
    }

    /**
     * Teacher on duty for this slot on this calendar day.
     * Null means the course is cancelled (substitution without a substitute).
     */
    public static function effectiveTeacherPersonId(TimetableSlot $slot, string $date): ?string
    {
        if (! TimetableSubstitution::tableReady()) {
            return $slot->teacher_person_id;
        }

        $substitution = TimetableSubstitution::query()
            ->where('timetable_slot_id', $slot->id)
            ->whereDate('on_date', $date)
            ->first();

        if ($substitution !== null) {
            return $substitution->substitute_person_id;
        }

        return $slot->teacher_person_id;
    }

    public static function isCancelled(TimetableSlot $slot, string $date): bool
    {
        if (! TimetableSubstitution::tableReady()) {
            return false;
        }

        return TimetableSubstitution::query()
            ->where('timetable_slot_id', $slot->id)
            ->whereDate('on_date', $date)
            ->whereNull('substitute_person_id')
            ->exists();
    }
}
