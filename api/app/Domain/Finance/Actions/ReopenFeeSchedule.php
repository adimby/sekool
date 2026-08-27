<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class ReopenFeeSchedule
{
    public function execute(string $schoolId, string $scheduleId, string $actorPersonId): FeeSchedule
    {
        return DB::transaction(function () use ($schoolId, $scheduleId, $actorPersonId): FeeSchedule {
            $schedule = FeeSchedule::query()->lockForUpdate()->find($scheduleId);
            if ($schedule === null || (string) $schedule->school_id !== $schoolId) {
                throw new DomainException('Barème introuvable.', 404);
            }

            if ($schedule->isLocked()) {
                throw new DomainException('Ce barème est verrouillé. Toute modification exige une demande de support FANABE.');
            }

            if ($schedule->status !== FeeScheduleStatus::PendingValidation) {
                throw new DomainException('Seul un barème en attente de validation peut être renvoyé en brouillon.');
            }

            $schedule->forceFill([
                'status' => FeeScheduleStatus::Draft,
                'submitted_at' => null,
                'submitted_by_person_id' => null,
            ])->save();

            Auditor::record('fee_schedule.reopened', 'fee_schedule', $schedule->id, null, [
                'actor_person_id' => $actorPersonId,
            ]);

            return $schedule->load(['items', 'gradeLevel', 'schoolYear']);
        });
    }
}
