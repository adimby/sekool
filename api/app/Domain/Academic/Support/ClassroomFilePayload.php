<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Enums\ClassActivityType;
use App\Domain\Academic\Enums\ClassCouncilStatus;
use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\ClassActivity;
use App\Domain\Academic\Models\ClassCouncil;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\ClassroomTeacher;
use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\Relationship;
use Illuminate\Support\Collection;

final class ClassroomFilePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function classroom(Classroom $classroom): array
    {
        $classroom->loadMissing(['gradeLevel', 'mainTeacher', 'delegate', 'viceDelegate']);

        return [
            'id' => $classroom->id,
            'school_id' => $classroom->school_id,
            'school_year_id' => $classroom->school_year_id,
            'grade_level_id' => $classroom->grade_level_id,
            'name' => $classroom->name,
            'capacity' => $classroom->capacity,
            'series' => $classroom->series,
            'main_teacher_person_id' => $classroom->main_teacher_person_id,
            'delegate_person_id' => $classroom->delegate_person_id,
            'vice_delegate_person_id' => $classroom->vice_delegate_person_id,
            'grade_level' => self::grade($classroom),
            'main_teacher' => PersonMini::make($classroom->mainTeacher),
            'delegate' => PersonMini::make($classroom->delegate),
            'vice_delegate' => PersonMini::make($classroom->viceDelegate),
        ];
    }

    /**
     * @return array{id: string, name: string, stage: string, stage_label: string|null, unit_label: string}|null
     */
    private static function grade(Classroom $classroom): ?array
    {
        $grade = $classroom->gradeLevel;
        if ($grade === null) {
            return null;
        }

        $stage = $grade->stage instanceof GradeStage
            ? $grade->stage
            : GradeStage::tryFrom((string) $grade->stage);

        return [
            'id' => $grade->id,
            'name' => $grade->name,
            'stage' => $stage?->value ?? 'middle',
            'stage_label' => $stage?->label(),
            'unit_label' => $stage?->unitLabel() ?? 'Classe',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function file(Classroom $classroom): array
    {
        $classroom->loadMissing([
            'gradeLevel',
            'mainTeacher',
            'delegate',
            'viceDelegate',
            'teachers.person',
            'timetableSlots.teacher',
            'councils.term',
            'activities',
        ]);

        $students = Enrollment::query()
            ->with('person')
            ->where('classroom_id', $classroom->id)
            ->where('status', EnrollmentStatus::Active)
            ->orderBy('student_number')
            ->get()
            ->map(fn (Enrollment $enrollment): array => [
                'enrollment_id' => $enrollment->id,
                'person_id' => $enrollment->person_id,
                'student_number' => $enrollment->student_number,
                'status' => $enrollment->status->value,
                'office' => match ($enrollment->person_id) {
                    $classroom->delegate_person_id => 'delegate',
                    $classroom->vice_delegate_person_id => 'vice_delegate',
                    default => null,
                },
                'person' => $enrollment->person === null ? null : [
                    'id' => $enrollment->person->id,
                    'public_id' => $enrollment->person->public_id,
                    'first_name' => $enrollment->person->first_name,
                    'last_name' => $enrollment->person->last_name,
                ],
            ])
            ->values();

        $stage = ClassroomCycle::of($classroom);

        return [
            'classroom' => self::classroom($classroom),
            'headcount' => $students->count(),
            'students' => $students,
            'pickup' => $stage === GradeStage::Preschool ? self::pickup($students) : [],
            'teachers' => $classroom->teachers
                ->sortBy(fn (ClassroomTeacher $row) => mb_strtolower($row->person?->last_name ?? ''))
                ->values()
                ->map(fn (ClassroomTeacher $row): array => [
                    'id' => $row->id,
                    'person_id' => $row->person_id,
                    'subject' => $row->subject,
                    'is_main' => $row->person_id === $classroom->main_teacher_person_id,
                    'person' => PersonMini::make($row->person),
                ]),
            'timetable' => $classroom->timetableSlots
                ->sortBy([
                    ['weekday', 'asc'],
                    ['starts_at', 'asc'],
                ])
                ->values()
                ->map(fn (TimetableSlot $slot): array => [
                    'id' => $slot->id,
                    'weekday' => $slot->weekday,
                    'starts_at' => substr((string) $slot->starts_at, 0, 5),
                    'ends_at' => substr((string) $slot->ends_at, 0, 5),
                    'subject' => $slot->subject,
                    'room' => $slot->room,
                    'teacher_person_id' => $slot->teacher_person_id,
                    'teacher' => PersonMini::make($slot->teacher),
                ]),
            'councils' => $classroom->councils
                ->sortByDesc(fn (ClassCouncil $row) => $row->held_on?->toDateString())
                ->values()
                ->map(fn (ClassCouncil $row): array => [
                    'id' => $row->id,
                    'academic_term_id' => $row->academic_term_id,
                    'term' => $row->term === null ? null : [
                        'id' => $row->term->id,
                        'label' => $row->term->label,
                        'sequence' => $row->term->sequence,
                    ],
                    'held_on' => $row->held_on?->toDateString(),
                    'title' => $row->title,
                    'minutes' => $row->minutes,
                    'status' => $row->status instanceof ClassCouncilStatus
                        ? $row->status->value
                        : (string) $row->status,
                ]),
            'activities' => $classroom->activities
                ->sortByDesc(fn (ClassActivity $row) => $row->held_on?->toDateString())
                ->values()
                ->map(function (ClassActivity $row): array {
                    $type = $row->type instanceof ClassActivityType
                        ? $row->type
                        : ClassActivityType::tryFrom((string) $row->type);

                    return [
                        'id' => $row->id,
                        'type' => $type?->value ?? 'other',
                        'title' => $row->title,
                        'held_on' => $row->held_on?->toDateString(),
                        'location' => $row->location,
                        'notes' => $row->notes,
                    ];
                }),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $students
     * @return list<array{student: mixed, adults: list<array{person: mixed, via: string}>}>
     */
    private static function pickup(Collection $students): array
    {
        $personIds = $students->pluck('person_id')->filter()->values();
        if ($personIds->isEmpty()) {
            return [];
        }

        $priority = [
            RelationshipType::PickupAuthorizedFor->value => 0,
            RelationshipType::GuardianOf->value => 1,
            RelationshipType::ParentOf->value => 2,
        ];

        $relations = Relationship::query()
            ->with('subject')
            ->whereIn('object_person_id', $personIds)
            ->whereIn('type', [
                RelationshipType::PickupAuthorizedFor,
                RelationshipType::ParentOf,
                RelationshipType::GuardianOf,
            ])
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->get();

        return $students
            ->map(function (array $row) use ($relations, $priority): array {
                $adults = $relations
                    ->filter(fn (Relationship $relation): bool => (string) $relation->object_person_id === (string) $row['person_id'])
                    ->sortBy(function (Relationship $relation) use ($priority): int {
                        $type = $relation->type instanceof RelationshipType
                            ? $relation->type->value
                            : (string) $relation->type;

                        return $priority[$type] ?? 9;
                    })
                    ->unique(fn (Relationship $relation): string => (string) $relation->subject_person_id)
                    ->values()
                    ->map(fn (Relationship $relation): array => [
                        'person' => PersonMini::make($relation->subject),
                        'via' => $relation->type instanceof RelationshipType
                            ? $relation->type->value
                            : (string) $relation->type,
                    ])
                    ->all();

                return [
                    'student' => $row['person'],
                    'adults' => $adults,
                ];
            })
            ->values()
            ->all();
    }
}
