<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Enums\ClassPostKind;
use App\Domain\Academic\Enums\DisciplinaryCaseStatus;
use App\Domain\Academic\Enums\DisciplinaryMeasureType;
use App\Domain\Academic\Enums\SchoolEventAudience;
use App\Domain\Academic\Enums\SchoolEventType;
use App\Domain\Academic\Models\ClassPost;
use App\Domain\Academic\Models\DisciplinaryCase;
use App\Domain\Academic\Models\SchoolEvent;

final class ClassLifePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function post(ClassPost $post): array
    {
        $kind = $post->kind instanceof ClassPostKind
            ? $post->kind
            : ClassPostKind::tryFrom((string) $post->kind);

        return [
            'id' => $post->id,
            'classroom_id' => $post->classroom_id,
            'kind' => $kind?->value ?? 'journal',
            'kind_label' => $kind?->label() ?? 'Cahier journal',
            'title' => $post->title,
            'body' => $post->body,
            'due_on' => $post->due_on?->toDateString(),
            'held_on' => $post->held_on?->toDateString(),
            'attachment_name' => $post->attachment_name,
            'attachment_body' => $post->attachment_body,
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function disciplinaryCase(DisciplinaryCase $case, bool $forFamily = false): array
    {
        $type = $case->measure_type instanceof DisciplinaryMeasureType
            ? $case->measure_type
            : DisciplinaryMeasureType::tryFrom((string) $case->measure_type);
        $status = $case->status instanceof DisciplinaryCaseStatus
            ? $case->status
            : DisciplinaryCaseStatus::tryFrom((string) $case->status);
        $person = $case->enrollment?->person;

        $payload = [
            'id' => $case->id,
            'enrollment_id' => $case->enrollment_id,
            'classroom_id' => $case->classroom_id,
            'occurred_on' => $case->occurred_on?->toDateString(),
            'fact' => $forFamily ? null : $case->fact,
            'measure_type' => $type?->value ?? 'other',
            'measure_type_label' => $type?->label() ?? 'Autre',
            'measure_label' => $case->measure_label,
            'measure_on' => $case->measure_on?->toDateString(),
            'status' => $status?->value ?? 'open',
            'status_label' => $status?->label() ?? 'En cours',
            'follow_up' => $forFamily ? null : $case->follow_up,
            'student' => $person === null ? null : [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
            ],
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function event(SchoolEvent $event): array
    {
        $type = $event->type instanceof SchoolEventType
            ? $event->type
            : SchoolEventType::tryFrom((string) $event->type);
        $audience = $event->audience instanceof SchoolEventAudience
            ? $event->audience
            : SchoolEventAudience::tryFrom((string) $event->audience);

        return [
            'id' => $event->id,
            'type' => $type?->value ?? 'other',
            'type_label' => $type?->label() ?? 'Autre',
            'title' => $event->title,
            'body' => $event->body,
            'starts_on' => $event->starts_on?->toDateString(),
            'ends_on' => $event->ends_on?->toDateString(),
            'audience' => $audience?->value ?? 'school',
            'audience_label' => $audience?->label() ?? 'Toute l’école',
            'grade_level_id' => $event->grade_level_id,
            'classroom_id' => $event->classroom_id,
            'location' => $event->location,
        ];
    }
}
