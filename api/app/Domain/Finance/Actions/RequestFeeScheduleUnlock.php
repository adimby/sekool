<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class RequestFeeScheduleUnlock
{
    public function execute(string $schoolId, string $scheduleId, string $actorPersonId, string $reason): FeeSchedule
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Précisez le motif de la demande de support.');
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
                'unlock_requested_at' => now(),
                'unlock_requested_by_person_id' => $actorPersonId,
                'unlock_request_reason' => $reason,
            ])->save();

            Auditor::record('fee_schedule.unlock_requested', 'fee_schedule', $schedule->id, null, [
                'actor_person_id' => $actorPersonId,
                'reason' => $reason,
            ]);

            return $schedule->load(['items', 'gradeLevel', 'schoolYear']);
        });
    }
}
