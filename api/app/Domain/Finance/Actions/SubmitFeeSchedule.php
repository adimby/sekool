<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class SubmitFeeSchedule
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

            if ($schedule->items->isEmpty()) {
                throw new DomainException('Un barème vide ne peut pas être soumis.');
            }

            $schedule->forceFill([
                'status' => FeeScheduleStatus::PendingValidation,
                'submitted_at' => now(),
                'submitted_by_person_id' => $actorPersonId,
            ])->save();

            Auditor::record('fee_schedule.submitted', 'fee_schedule', $schedule->id, null, [
                'actor_person_id' => $actorPersonId,
            ]);

            return $schedule->load(['items', 'gradeLevel', 'schoolYear']);
        });
    }
}
