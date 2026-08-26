<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Models\CollectionTask;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class CloseCollectionTask
{
    public function execute(
        string $schoolId,
        string $taskId,
        string $status,
        ?string $notes = null,
    ): CollectionTask {
        if (! in_array($status, ['resolved', 'dismissed'], true)) {
            throw new DomainException('Statut de clôture invalide.');
        }

        $task = CollectionTask::query()->find($taskId);
        if ($task === null || (string) $task->school_id !== $schoolId) {
            throw new DomainException('Action introuvable.', 404);
        }

        $task->forceFill([
            'status' => $status,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ])->save();

        $task->load('enrollment');

        Auditor::record(
            'collection.task.'.$status,
            'collection_task',
            $task->id,
            $task->enrollment?->person_id,
            ['notes' => $notes],
        );

        return $task->refresh();
    }
}
