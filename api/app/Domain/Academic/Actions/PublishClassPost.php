<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\ClassPostKind;
use App\Domain\Academic\Models\ClassPost;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\School;
use Carbon\Carbon;

final class PublishClassPost
{
    public function __construct(private readonly NotifyClassFamilies $notify) {}

    /**
     * @param  array{
     *     kind: string,
     *     title: string,
     *     body: string,
     *     due_on?: string|null,
     *     held_on?: string|null,
     *     attachment_name?: string|null,
     *     attachment_body?: string|null
     * }  $data
     */
    public function execute(string $schoolId, string $classroomId, string $actorPersonId, array $data): ClassPost
    {
        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        $kind = ClassPostKind::tryFrom($data['kind']);
        if ($kind === null) {
            throw new DomainException('Type de publication inconnu.');
        }

        $title = trim($data['title']);
        $body = trim($data['body']);
        if ($title === '' || $body === '') {
            throw new DomainException('Indiquez un titre et un texte.');
        }

        $dueOn = isset($data['due_on']) && is_string($data['due_on']) && trim($data['due_on']) !== ''
            ? trim($data['due_on'])
            : null;
        $heldOn = isset($data['held_on']) && is_string($data['held_on']) && trim($data['held_on']) !== ''
            ? trim($data['held_on'])
            : null;

        if ($kind === ClassPostKind::Homework) {
            if ($dueOn === null) {
                throw new DomainException('Indiquez la date du devoir.');
            }
            $heldOn = null;
        } else {
            if ($heldOn === null) {
                throw new DomainException('Indiquez la date du cahier journal.');
            }
            $dueOn = null;
        }

        $attachmentName = isset($data['attachment_name']) && trim((string) $data['attachment_name']) !== ''
            ? trim((string) $data['attachment_name'])
            : null;
        $attachmentBody = isset($data['attachment_body']) && trim((string) $data['attachment_body']) !== ''
            ? (string) $data['attachment_body']
            : null;
        if ($attachmentName === null) {
            $attachmentBody = null;
        }

        $post = ClassPost::query()->create([
            'school_id' => $schoolId,
            'classroom_id' => $classroomId,
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'due_on' => $dueOn,
            'held_on' => $heldOn,
            'attachment_name' => $attachmentName,
            'attachment_body' => $attachmentBody,
            'created_by_person_id' => $actorPersonId,
        ]);

        $date = Carbon::parse($dueOn ?? $heldOn)->format('d/m/Y');
        $enrollments = Enrollment::query()
            ->with('person')
            ->where('classroom_id', $classroomId)
            ->where('status', EnrollmentStatus::Active)
            ->get();

        $this->notify->execute(
            schoolId: $schoolId,
            templateKey: $kind->familyTemplate(),
            enrollments: $enrollments,
            channels: $kind->familyChannels(),
            variables: [
                'title' => $title,
                'date' => $date,
                'school_name' => School::query()->find($schoolId)?->name ?? 'l’école',
            ],
            sourceId: (string) $post->id,
        );

        Auditor::record('class_post.published', 'class_post', $post->id, null, [
            'classroom_id' => $classroomId,
            'kind' => $kind->value,
        ]);

        return $post;
    }
}
