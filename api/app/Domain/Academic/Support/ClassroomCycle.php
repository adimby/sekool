<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\TimetableSlot;

final class ClassroomCycle
{
    public static function of(Classroom $classroom): GradeStage
    {
        $classroom->loadMissing('gradeLevel');

        $stage = $classroom->gradeLevel?->stage;
        if ($stage instanceof GradeStage) {
            return $stage;
        }

        return GradeStage::tryFrom((string) $stage) ?? GradeStage::Middle;
    }

    public static function usesLivret(Classroom $classroom): bool
    {
        return self::of($classroom)->usesLivret();
    }

    public static function takesAttendanceByPeriod(Classroom $classroom): bool
    {
        return self::of($classroom)->takesAttendanceByPeriod();
    }

    /**
     * Collège / lycée with an emploi du temps: attendance must name the course.
     * Classes without slots yet keep day attendance so setup and existing tests still work.
     */
    public static function requiresCourseForAttendance(Classroom $classroom): bool
    {
        return self::takesAttendanceByPeriod($classroom)
            && TimetableSlot::query()->where('classroom_id', $classroom->id)->exists();
    }
}
