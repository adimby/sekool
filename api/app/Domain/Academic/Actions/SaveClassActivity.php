<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\ClassActivityType;
use App\Domain\Academic\Models\ClassActivity;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class SaveClassActivity
{
    /**
     * @param  array{
     *     type: string,
     *     title: string,
     *     held_on: string,
     *     location?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function execute(string $schoolId, string $classroomId, array $data, ?string $activityId = null): ClassActivity
    {
        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        $type = ClassActivityType::tryFrom($data['type']);
        if ($type === null) {
            throw new DomainException('Type d’activité inconnu.');
        }

        $attrs = [
            'type' => $type,
            'title' => trim($data['title']),
            'held_on' => $data['held_on'],
            'location' => isset($data['location']) && trim((string) $data['location']) !== '' ? trim((string) $data['location']) : null,
            'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ];

        if ($activityId !== null) {
            $activity = ClassActivity::query()->where('classroom_id', $classroomId)->find($activityId);
            if ($activity === null) {
                throw new DomainException('Activité introuvable.', 404);
            }
            $activity->fill($attrs)->save();
        } else {
            $activity = ClassActivity::query()->create([
                'school_id' => $schoolId,
                'classroom_id' => $classroomId,
                ...$attrs,
            ]);
        }

        Auditor::record('class_activity.saved', 'class_activity', $activity->id, null, [
            'classroom_id' => $classroomId,
            'type' => $type->value,
        ]);

        return $activity;
    }
}
