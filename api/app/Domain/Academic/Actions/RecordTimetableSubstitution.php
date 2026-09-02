<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Academic\Models\TimetableSubstitution;
use App\Domain\Academic\Support\ClassroomPeople;
use App\Domain\Communication\Support\MessageRenderer;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\School;
use Carbon\Carbon;

final class RecordTimetableSubstitution
{
    public function __construct(private readonly NotifyClassFamilies $notify) {}

    /**
     * @param  array{
     *     on_date: string,
     *     substitute_person_id?: string|null,
     *     reason?: string|null
     * }  $data
     */
    public function execute(string $schoolId, string $slotId, string $actorPersonId, array $data): TimetableSubstitution
    {
        $slot = TimetableSlot::query()->with('classroom')->find($slotId);
        if ($slot === null || (string) $slot->school_id !== $schoolId) {
            throw new DomainException('Créneau introuvable.', 404);
        }

        $onDate = Carbon::parse($data['on_date'])->startOfDay();
        if ((int) $onDate->isoWeekday() !== (int) $slot->weekday) {
            throw new DomainException('La date ne correspond pas au jour du créneau.');
        }

        $substituteId = isset($data['substitute_person_id']) && is_string($data['substitute_person_id']) && $data['substitute_person_id'] !== ''
            ? $data['substitute_person_id']
            : null;
        if ($substituteId !== null) {
            ClassroomPeople::assertStaff($schoolId, $substituteId);
        }

        $reason = isset($data['reason']) && trim((string) $data['reason']) !== ''
            ? trim((string) $data['reason'])
            : null;
        if ($reason !== null) {
            MessageRenderer::assertFamilySafe($reason);
        }
        if ($substituteId === null && $reason === null) {
            throw new DomainException('Indiquez un remplaçant ou le motif d’annulation.');
        }

        $row = TimetableSubstitution::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'timetable_slot_id' => $slot->id,
                'on_date' => $onDate->toDateString(),
            ],
            [
                'classroom_id' => $slot->classroom_id,
                'substitute_person_id' => $substituteId,
                'reason' => $reason,
                'created_by_person_id' => $actorPersonId,
            ],
        );

        $enrollments = Enrollment::query()
            ->where('classroom_id', $slot->classroom_id)
            ->where('status', EnrollmentStatus::Active)
            ->get();

        $this->notify->execute(
            schoolId: $schoolId,
            templateKey: 'timetable_substitution',
            enrollments: $enrollments,
            channels: ['in_app'],
            variables: [
                'subject' => $slot->subject,
                'date' => $onDate->format('d/m/Y'),
                'school_name' => School::query()->find($schoolId)?->name ?? 'l’école',
            ],
            sourceId: (string) $row->id,
        );

        Auditor::record('timetable.substituted', 'timetable_substitution', $row->id, null, [
            'classroom_id' => $slot->classroom_id,
            'on_date' => $onDate->toDateString(),
        ]);

        return $row->load(['substitute', 'slot']);
    }
}
