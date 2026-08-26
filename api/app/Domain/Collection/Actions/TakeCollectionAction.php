<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Models\CollectionTask;
use App\Domain\Collection\Support\EnrollmentInstallments;
use App\Domain\Collection\Support\FamilyRecipients;
use App\Domain\Collection\Support\QuietHours;
use App\Domain\Communication\Actions\DispatchFamilyMessage;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\School;

final class TakeCollectionAction
{
    public function __construct(private readonly DispatchFamilyMessage $messages) {}

    public function execute(string $schoolId, string $taskId, string $actorPersonId): CollectionTask
    {
        $task = CollectionTask::query()->find($taskId);
        if ($task === null || (string) $task->school_id !== $schoolId) {
            throw new DomainException('Action introuvable.', 404);
        }

        if (in_array($task->status, ['resolved', 'dismissed'], true)) {
            throw new DomainException('Cette action est déjà close.');
        }

        $task->forceFill([
            'status' => 'in_progress',
            'claimed_by_person_id' => $actorPersonId,
            'claimed_at' => $task->claimed_at ?? now(),
        ])->save();

        $enrollment = Enrollment::query()->with('person')->find($task->enrollment_id);
        $student = $enrollment?->person;
        if ($student !== null) {
            $schoolName = School::query()->find($schoolId)?->name ?? 'l’école';
            $quiet = QuietHours::isQuiet();
            $variables = [
                'student_first_name' => $student->first_name,
                'student_last_name' => $student->last_name,
                'school_name' => $schoolName,
                'remaining_amount' => (string) ($enrollment ? EnrollmentInstallments::remaining((string) $enrollment->id) : 0),
            ];
            foreach (FamilyRecipients::adultsForStudent((string) $student->id) as $adult) {
                foreach (['in_app', 'print'] as $channel) {
                    $this->messages->execute(
                        schoolId: $schoolId,
                        templateKey: $task->template_key,
                        channel: $channel,
                        subjectPersonId: (string) $student->id,
                        recipientPersonId: (string) $adult->id,
                        variables: $variables,
                        idempotencyKey: $task->template_key.':'.$channel.':'.$task->enrollment_id.':'.$adult->id.':'.QuietHours::today(),
                        deliverNow: $channel === 'print' || ! $quiet,
                        workflowRunId: $task->workflow_run_id,
                        priority: $task->priority,
                    );
                }
            }
        }

        Auditor::record(
            'collection.task.relanced',
            'collection_task',
            $task->id,
            $enrollment?->person_id,
            [
                'template_key' => $task->template_key,
                'priority' => $task->priority,
            ],
        );

        return $task->refresh()->load('enrollment.person');
    }
}
