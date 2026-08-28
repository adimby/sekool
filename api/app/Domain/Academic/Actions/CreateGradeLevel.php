<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class CreateGradeLevel
{
    public function execute(string $schoolId, string $name, GradeStage $stage, int $sequence): GradeLevel
    {
        $existing = GradeLevel::query()->where('name', $name)->first();
        if ($existing !== null) {
            throw new DomainException('Ce niveau existe déjà dans l\'établissement.', 409);
        }

        $grade = GradeLevel::query()->create([
            'school_id' => $schoolId,
            'name' => $name,
            'stage' => $stage,
            'sequence' => $sequence,
        ]);

        Auditor::record('grade_level.created', 'grade_level', $grade->id);

        return $grade;
    }
}
