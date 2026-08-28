<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\StudentAlertStatus;
use App\Domain\Academic\Models\StudentAlert;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class AcknowledgeStudentAlert
{
    public function execute(StudentAlert $alert, string $actorPersonId): StudentAlert
    {
        if ($alert->status !== StudentAlertStatus::Open) {
            throw new DomainException('Ce signalement n’est plus ouvert.');
        }

        $alert->forceFill([
            'status' => StudentAlertStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by_person_id' => $actorPersonId,
        ])->save();

        Auditor::record('student_alert.acknowledged', 'student_alert', $alert->id, $alert->enrollment?->person_id);

        return $alert->refresh();
    }
}
