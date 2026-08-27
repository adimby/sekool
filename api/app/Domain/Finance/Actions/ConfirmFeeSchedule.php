<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class ConfirmFeeSchedule
{
    public function execute(string $schoolId, string $scheduleId, string $actorPersonId): FeeSchedule
    {
        return DB::transaction(function () use ($schoolId, $scheduleId, $actorPersonId): FeeSchedule {
            $schedule = FeeSchedule::query()->with('items')->lockForUpdate()->find($scheduleId);
            if ($schedule === null || (string) $schedule->school_id !== $schoolId) {
                throw new DomainException('Barème introuvable.', 404);
            }

            if ($schedule->isLocked()) {
                throw new DomainException('Ce barème est déjà verrouillé.');
            }

            if ($schedule->status !== FeeScheduleStatus::PendingValidation) {
                throw new DomainException('La deuxième validation n’est possible qu’après une première soumission.');
            }

            $schedule->forceFill([
                'status' => FeeScheduleStatus::Active,
                'locked_at' => now(),
                'locked_by_person_id' => $actorPersonId,
                'unlock_requested_at' => null,
                'unlock_requested_by_person_id' => null,
                'unlock_request_reason' => null,
            ])->save();

            Auditor::record('fee_schedule.locked', 'fee_schedule', $schedule->id, null, [
                'actor_person_id' => $actorPersonId,
                'submitted_by_person_id' => $schedule->submitted_by_person_id,
            ]);

            return $schedule->load(['items', 'gradeLevel', 'schoolYear']);
        });
    }
}
