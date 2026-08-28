<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Finance\Support\SyncFeeItems;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateFeeSchedule
{
    public function __construct(private readonly SyncFeeItems $syncItems) {}

    /**
     * @param  array{
     *     name?: string,
     *     grade_level_id?: string|null,
     *     items?: list<array<string, mixed>>
     * }  $data
     */
    public function execute(string $schoolId, string $scheduleId, array $data): FeeSchedule
    {
        return DB::transaction(function () use ($schoolId, $scheduleId, $data): FeeSchedule {
            $schedule = FeeSchedule::query()->lockForUpdate()->find($scheduleId);
            if ($schedule === null || (string) $schedule->school_id !== $schoolId) {
                throw new DomainException('Barème introuvable.', 404);
            }

            if ($schedule->isLocked()) {
                throw new DomainException('Ce barème est verrouillé. Toute modification exige une demande de support FANABE.');
            }

            if (array_key_exists('grade_level_id', $data)) {
                $gradeLevelId = $data['grade_level_id'];
                if ($gradeLevelId !== null && $gradeLevelId !== '') {
                    $grade = GradeLevel::query()->find($gradeLevelId);
                    if ($grade === null || (string) $grade->school_id !== $schoolId) {
                        throw new DomainException('Niveau introuvable.', 404);
                    }
                    $schedule->grade_level_id = $gradeLevelId;
                } else {
                    $schedule->grade_level_id = null;
                }

                $taken = FeeSchedule::query()
                    ->where('school_year_id', $schedule->school_year_id)
                    ->whereKeyNot($schedule->id)
                    ->when(
                        $schedule->grade_level_id === null,
                        fn ($query) => $query->whereNull('grade_level_id'),
                        fn ($query) => $query->where('grade_level_id', $schedule->grade_level_id),
                    )
                    ->exists();

                if ($taken) {
                    throw new DomainException('Un barème existe déjà pour ce niveau et cette année scolaire.', 409);
                }
            }

            if (isset($data['name']) && trim($data['name']) !== '') {
                $schedule->name = trim($data['name']);
            }

            if ($schedule->status === FeeScheduleStatus::PendingValidation) {
                $schedule->status = FeeScheduleStatus::Draft;
                $schedule->submitted_at = null;
                $schedule->submitted_by_person_id = null;
            }

            $schedule->save();

            if (isset($data['items'])) {
                $this->syncItems->execute($schedule, $data['items']);
            }

            Auditor::record('fee_schedule.updated', 'fee_schedule', $schedule->id);

            return $schedule->load(['items', 'gradeLevel', 'schoolYear']);
        });
    }
}
