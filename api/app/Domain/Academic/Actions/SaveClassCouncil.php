<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\ClassCouncilStatus;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\ClassCouncil;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class SaveClassCouncil
{
    /**
     * @param  array{
     *     academic_term_id?: string|null,
     *     held_on: string,
     *     title: string,
     *     minutes?: string|null,
     *     status?: string
     * }  $data
     */
    public function execute(string $schoolId, string $classroomId, array $data, ?string $councilId = null): ClassCouncil
    {
        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        $termId = $data['academic_term_id'] ?? null;
        if (is_string($termId) && $termId !== '') {
            $term = AcademicTerm::query()->find($termId);
            if ($term === null || (string) $term->school_year_id !== (string) $classroom->school_year_id) {
                throw new DomainException('Trimestre introuvable pour cette année.');
            }
        } else {
            $termId = null;
        }

        $status = ClassCouncilStatus::tryFrom((string) ($data['status'] ?? ClassCouncilStatus::Scheduled->value))
            ?? ClassCouncilStatus::Scheduled;

        $attrs = [
            'academic_term_id' => $termId,
            'held_on' => $data['held_on'],
            'title' => trim($data['title']),
            'minutes' => isset($data['minutes']) && trim((string) $data['minutes']) !== '' ? trim((string) $data['minutes']) : null,
            'status' => $status,
        ];

        if ($councilId !== null) {
            $council = ClassCouncil::query()->where('classroom_id', $classroomId)->find($councilId);
            if ($council === null) {
                throw new DomainException('Conseil de classe introuvable.', 404);
            }
            $council->fill($attrs)->save();
        } else {
            $council = ClassCouncil::query()->create([
                'school_id' => $schoolId,
                'classroom_id' => $classroomId,
                ...$attrs,
            ]);
        }

        Auditor::record('class_council.saved', 'class_council', $council->id, null, [
            'classroom_id' => $classroomId,
        ]);

        return $council->load('term');
    }
}
