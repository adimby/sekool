<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Finance\Support\SyncFeeItems;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class CreateFeeSchedule
{
    public function __construct(private readonly SyncFeeItems $syncItems) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function execute(
        string $schoolId,
        string $schoolYearId,
        ?string $gradeLevelId,
        string $name,
        array $items,
        ?string $copiedFromScheduleId = null,
    ): FeeSchedule {
        $year = SchoolYear::query()->find($schoolYearId);
        if ($year === null || (string) $year->school_id !== $schoolId) {
            throw new DomainException('Année scolaire introuvable.', 404);
        }

        if ($gradeLevelId !== null && $gradeLevelId !== '') {
            $grade = GradeLevel::query()->find($gradeLevelId);
            if ($grade === null || (string) $grade->school_id !== $schoolId) {
                throw new DomainException('Niveau introuvable.', 404);
            }
        } else {
            $gradeLevelId = null;
        }

        $this->assertUniqueSlot($schoolYearId, $gradeLevelId);

        try {
            return DB::transaction(function () use (
                $schoolId,
                $schoolYearId,
                $gradeLevelId,
                $name,
                $items,
                $copiedFromScheduleId,
            ): FeeSchedule {
                $schedule = FeeSchedule::query()->create([
                    'school_id' => $schoolId,
                    'school_year_id' => $schoolYearId,
                    'grade_level_id' => $gradeLevelId,
                    'name' => $name,
                    'status' => FeeScheduleStatus::Draft,
                    'copied_from_schedule_id' => $copiedFromScheduleId,
                ]);

                $this->syncItems->execute($schedule, $items);

                Auditor::record('fee_schedule.created', 'fee_schedule', $schedule->id, null, [
                    'school_year_id' => $schoolYearId,
                    'grade_level_id' => $gradeLevelId,
                    'copied_from_schedule_id' => $copiedFromScheduleId,
                ]);

                return $schedule->load(['items', 'gradeLevel', 'schoolYear']);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Un barème existe déjà pour ce niveau et cette année scolaire.', 409);
        }
    }

    private function assertUniqueSlot(string $schoolYearId, ?string $gradeLevelId): void
    {
        $query = FeeSchedule::query()->where('school_year_id', $schoolYearId);
        if ($gradeLevelId === null) {
            $query->whereNull('grade_level_id');
        } else {
            $query->where('grade_level_id', $gradeLevelId);
        }

        if ($query->exists()) {
            throw new DomainException('Un barème existe déjà pour ce niveau et cette année scolaire.', 409);
        }
    }
}
