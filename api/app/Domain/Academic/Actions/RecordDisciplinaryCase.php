<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\DisciplinaryCaseStatus;
use App\Domain\Academic\Enums\DisciplinaryMeasureType;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\DisciplinaryCase;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\School;
use Carbon\Carbon;

final class RecordDisciplinaryCase
{
    public function __construct(private readonly NotifyClassFamilies $notify) {}

    /**
     * @param  array{
     *     enrollment_id: string,
     *     occurred_on: string,
     *     fact: string,
     *     measure_type: string,
     *     measure_label?: string|null,
     *     measure_on?: string|null,
     *     follow_up?: string|null
     * }  $data
     */
    public function execute(string $schoolId, string $classroomId, string $actorPersonId, array $data): DisciplinaryCase
    {
        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        $enrollment = Enrollment::query()->with('person')->find($data['enrollment_id']);
        if (
            $enrollment === null
            || (string) $enrollment->classroom_id !== $classroomId
            || $enrollment->status !== EnrollmentStatus::Active
        ) {
            throw new DomainException('Élève introuvable dans cette classe.', 404);
        }

        $type = DisciplinaryMeasureType::tryFrom($data['measure_type']);
        if ($type === null) {
            throw new DomainException('Type de mesure inconnu.');
        }

        $fact = trim($data['fact']);
        if ($fact === '') {
            throw new DomainException('Décrivez le constat.');
        }

        $label = isset($data['measure_label']) && trim((string) $data['measure_label']) !== ''
            ? trim((string) $data['measure_label'])
            : $type->label();

        $followUp = isset($data['follow_up']) && trim((string) $data['follow_up']) !== ''
            ? trim((string) $data['follow_up'])
            : null;

        $measureOn = isset($data['measure_on']) && is_string($data['measure_on']) && trim($data['measure_on']) !== ''
            ? trim($data['measure_on'])
            : null;

        $case = DisciplinaryCase::query()->create([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollment->id,
            'classroom_id' => $classroomId,
            'occurred_on' => $data['occurred_on'],
            'fact' => $fact,
            'measure_type' => $type,
            'measure_label' => $label,
            'measure_on' => $measureOn,
            'status' => DisciplinaryCaseStatus::Open,
            'follow_up' => $followUp,
            'created_by_person_id' => $actorPersonId,
        ]);

        $this->notify->execute(
            schoolId: $schoolId,
            templateKey: 'discipline_measure',
            enrollments: [$enrollment],
            channels: ['in_app', 'print'],
            variables: [
                'date' => Carbon::parse($data['occurred_on'])->format('d/m/Y'),
                'measure' => $label,
                'school_name' => School::query()->find($schoolId)?->name ?? 'l’école',
            ],
            sourceId: (string) $case->id,
        );

        Auditor::record('disciplinary_case.recorded', 'disciplinary_case', $case->id, (string) $enrollment->person_id, [
            'classroom_id' => $classroomId,
            'measure_type' => $type->value,
        ]);

        return $case->load('enrollment.person');
    }
}
