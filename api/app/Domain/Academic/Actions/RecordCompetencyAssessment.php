<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\CompetencyLevel;
use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\CompetencyAssessment;
use App\Domain\Academic\Models\CompetencyItem;
use App\Domain\Academic\Support\ClassroomCycle;
use App\Domain\Communication\Support\MessageRenderer;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class RecordCompetencyAssessment
{
    /**
     * @param  array{
     *     enrollment_id: string,
     *     competency_item_id: string,
     *     level: string,
     *     comment?: string|null,
     *     assessed_on: string,
     *     academic_term_id?: string|null
     * }  $input
     */
    public function execute(string $schoolId, string $classroomId, string $actorPersonId, array $input): CompetencyAssessment
    {
        $classroom = Classroom::query()->with('gradeLevel')->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        $stage = ClassroomCycle::of($classroom);
        if (! $stage->usesLivret()) {
            throw new DomainException('Le livret de compétences s’applique à la maternelle et au primaire.');
        }

        $enrollment = Enrollment::query()->find($input['enrollment_id']);
        if (
            $enrollment === null
            || (string) $enrollment->classroom_id !== $classroomId
            || $enrollment->status !== EnrollmentStatus::Active
        ) {
            throw new DomainException('Élève introuvable dans cette classe.', 404);
        }

        $item = CompetencyItem::query()->with('domain')->find($input['competency_item_id']);
        if ($item === null || (string) $item->school_id !== $schoolId) {
            throw new DomainException('Compétence introuvable.', 404);
        }
        if ($item->domain === null || $item->domain->stage !== $stage) {
            throw new DomainException('Cette compétence n’appartient pas à ce cycle.');
        }

        $level = CompetencyLevel::tryFrom($input['level']);
        if ($level === null) {
            throw new DomainException('Niveau inconnu. Utilisez Pas encore, En cours ou Acquis.');
        }

        $comment = isset($input['comment']) && trim((string) $input['comment']) !== ''
            ? trim((string) $input['comment'])
            : null;
        if ($comment !== null) {
            MessageRenderer::assertFamilySafe($comment);
        }

        $assessment = CompetencyAssessment::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'enrollment_id' => $enrollment->id,
                'competency_item_id' => $item->id,
            ],
            [
                'classroom_id' => $classroomId,
                'academic_term_id' => $input['academic_term_id'] ?? null,
                'level' => $level,
                'comment' => $comment,
                'assessed_on' => $input['assessed_on'],
                'recorded_by_person_id' => $actorPersonId,
            ],
        );

        Auditor::record('competency.recorded', 'competency_assessment', $assessment->id, (string) $enrollment->person_id, [
            'classroom_id' => $classroomId,
            'level' => $level->value,
        ]);

        return $assessment->load('item');
    }
}
