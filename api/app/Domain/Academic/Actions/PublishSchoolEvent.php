<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\SchoolEventAudience;
use App\Domain\Academic\Enums\SchoolEventType;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\SchoolEvent;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\School;
use Carbon\Carbon;

final class PublishSchoolEvent
{
    public function __construct(private readonly NotifyClassFamilies $notify) {}

    /**
     * @param  array{
     *     type: string,
     *     title: string,
     *     body?: string|null,
     *     starts_on: string,
     *     ends_on?: string|null,
     *     audience: string,
     *     grade_level_id?: string|null,
     *     classroom_id?: string|null,
     *     location?: string|null
     * }  $data
     */
    public function execute(string $schoolId, string $actorPersonId, array $data): SchoolEvent
    {
        $type = SchoolEventType::tryFrom($data['type']);
        if ($type === null) {
            throw new DomainException('Type d’événement inconnu.');
        }

        $audience = SchoolEventAudience::tryFrom($data['audience']);
        if ($audience === null) {
            throw new DomainException('Destinataires inconnus.');
        }

        $title = trim($data['title']);
        if ($title === '') {
            throw new DomainException('Indiquez un titre.');
        }

        $body = isset($data['body']) && trim((string) $data['body']) !== ''
            ? trim((string) $data['body'])
            : null;
        $location = isset($data['location']) && trim((string) $data['location']) !== ''
            ? trim((string) $data['location'])
            : null;
        $endsOn = isset($data['ends_on']) && is_string($data['ends_on']) && trim($data['ends_on']) !== ''
            ? trim($data['ends_on'])
            : null;

        $gradeLevelId = isset($data['grade_level_id']) && is_string($data['grade_level_id']) && $data['grade_level_id'] !== ''
            ? $data['grade_level_id']
            : null;
        $classroomId = isset($data['classroom_id']) && is_string($data['classroom_id']) && $data['classroom_id'] !== ''
            ? $data['classroom_id']
            : null;

        if ($audience === SchoolEventAudience::School) {
            $gradeLevelId = null;
            $classroomId = null;
        } elseif ($audience === SchoolEventAudience::Grade) {
            $classroomId = null;
            if ($gradeLevelId === null || GradeLevel::query()->find($gradeLevelId) === null) {
                throw new DomainException('Niveau introuvable.', 404);
            }
        } else {
            $classroom = $classroomId === null ? null : Classroom::query()->find($classroomId);
            if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
                throw new DomainException('Classe introuvable.', 404);
            }
            $gradeLevelId = $classroom->grade_level_id;
        }

        $event = SchoolEvent::query()->create([
            'school_id' => $schoolId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'starts_on' => $data['starts_on'],
            'ends_on' => $endsOn,
            'audience' => $audience,
            'grade_level_id' => $gradeLevelId,
            'classroom_id' => $audience === SchoolEventAudience::Classroom ? $classroomId : null,
            'location' => $location,
            'created_by_person_id' => $actorPersonId,
        ]);

        $enrollments = $this->audienceEnrollments($schoolId, $audience, $gradeLevelId, $event->classroom_id);

        $this->notify->execute(
            schoolId: $schoolId,
            templateKey: 'school_event',
            enrollments: $enrollments,
            channels: ['in_app', 'print'],
            variables: [
                'title' => $title,
                'date' => Carbon::parse($data['starts_on'])->format('d/m/Y'),
                'school_name' => School::query()->find($schoolId)?->name ?? 'l’école',
            ],
            sourceId: (string) $event->id,
        );

        Auditor::record('school_event.published', 'school_event', $event->id, null, [
            'audience' => $audience->value,
            'type' => $type->value,
        ]);

        return $event;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Enrollment>
     */
    private function audienceEnrollments(
        string $schoolId,
        SchoolEventAudience $audience,
        ?string $gradeLevelId,
        ?string $classroomId,
    ) {
        $query = Enrollment::query()
            ->with('person')
            ->where('school_id', $schoolId)
            ->where('status', EnrollmentStatus::Active);

        if ($audience === SchoolEventAudience::Classroom) {
            $query->where('classroom_id', $classroomId);
        } elseif ($audience === SchoolEventAudience::Grade) {
            $classroomIds = Classroom::query()->where('grade_level_id', $gradeLevelId)->pluck('id');
            $query->whereIn('classroom_id', $classroomIds);
        }

        return $query->get();
    }
}
