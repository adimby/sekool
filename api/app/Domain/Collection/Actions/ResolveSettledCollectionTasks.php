<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Models\CollectionTask;
use App\Domain\Collection\Support\EnrollmentInstallments;

final class ResolveSettledCollectionTasks
{
    public function execute(string $schoolId, string $enrollmentId): void
    {
        if (EnrollmentInstallments::remaining($enrollmentId) > 0) {
            return;
        }

        CollectionTask::query()
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('template_key', 'payment_overdue')
            ->whereIn('status', ['open', 'in_progress'])
            ->each(function (CollectionTask $task): void {
                $task->forceFill([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolution_notes' => 'Échéance soldée.',
                ])->save();
            });
    }
}
