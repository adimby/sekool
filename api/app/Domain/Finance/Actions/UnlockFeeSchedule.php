<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Support-only path. Not exposed on school HTTP routes.
 * After unlock the barème returns to draft and must be re-validated twice.
 */
final class UnlockFeeSchedule
{
    public function execute(string $schoolId, string $scheduleId, string $actorPersonId, string $reason): FeeSchedule
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Le support doit indiquer un motif de déverrouillage.');
        }

        return DB::transaction(function () use ($schoolId, $scheduleId, $actorPersonId, $reason): FeeSchedule {
            $schedule = FeeSchedule::query()->lockForUpdate()->find($scheduleId);
            if ($schedule === null || (string) $schedule->school_id !== $schoolId) {
                throw new DomainException('Barème introuvable.', 404);
            }

            if (! $schedule->isLocked()) {
                throw new DomainException('Ce barème n’est pas verrouillé.');
            }

            $schedule->forceFill([
                'status' => FeeScheduleStatus::Draft,
                'locked_at' => null,
                'locked_by_person_id' => null,
                'submitted_at' => null,
                'submitted_by_person_id' => null,
                'unlock_requested_at' => null,
                'unlock_requested_by_person_id' => null,
                'unlock_request_reason' => null,
            ])->save();

            Auditor::record('fee_schedule.unlocked', 'fee_schedule', $schedule->id, null, [
                'actor_person_id' => $actorPersonId,
                'reason' => $reason,
            ]);

            return $schedule->load(['items', 'gradeLevel', 'schoolYear']);
        });
    }
}
