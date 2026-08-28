<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\Classroom;

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
}
