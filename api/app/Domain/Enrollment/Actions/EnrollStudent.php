<?php

namespace App\Domain\Enrollment\Actions;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentStatusChange;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Identity\Actions\GrantSchoolPersonLink;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EnrollStudent
{
    public function execute(
        string $schoolId,
        string $schoolYearId,
        string $studentPersonId,
        string $actorPersonId,
        ?string $studentNumber = null,
        bool $skipAuthorization = false,
        ?string $classroomId = null,
    ): Enrollment|EnrollmentTransfer {
        SchoolYear::query()->findOrFail($schoolYearId);

        if (! $skipAuthorization && ! $this->hasStudentLink($schoolId, $studentPersonId)) {
            throw new DomainException('Aucun lien ne permet d\'inscrire cette personne.', 403);
        }

        $resolvedClassroomId = $this->validatedClassroomId($schoolId, $schoolYearId, $classroomId);

        $active = $this->activeEnrollment($studentPersonId);

        if ($active !== null) {
            if ($active->school_id === $schoolId) {
                return $active;
            }

            return app(RequestEnrollmentTransfer::class)->execute(
                originEnrollment: $active,
                destinationSchoolId: $schoolId,
                destinationSchoolYearId: $schoolYearId,
                actorPersonId: $actorPersonId,
                studentNumber: $studentNumber,
            );
        }

        return $this->createActive($schoolId, $schoolYearId, $studentPersonId, $actorPersonId, $studentNumber, $resolvedClassroomId);
    }

    private function hasStudentLink(string $schoolId, string $studentPersonId): bool
    {
        return SchoolPersonLink::query()
            ->where('school_id', $schoolId)
            ->where('person_id', $studentPersonId)
            ->where('kind', SchoolPersonLinkKind::Student)
            ->exists();
    }

    private function activeEnrollment(string $studentPersonId): ?Enrollment
    {
        return TenantContext::runWithRlsBypass(fn (): ?Enrollment => Enrollment::query()
            ->withoutGlobalScopes()
            ->where('person_id', $studentPersonId)
            ->where('status', EnrollmentStatus::Active)
            ->first());
    }

    private function createActive(
        string $schoolId,
        string $schoolYearId,
        string $studentPersonId,
        string $actorPersonId,
        ?string $studentNumber,
        ?string $classroomId,
    ): Enrollment {
        try {
            return DB::transaction(function () use ($schoolId, $schoolYearId, $studentPersonId, $actorPersonId, $studentNumber, $classroomId): Enrollment {
                $enrollment = Enrollment::query()->create([
                    'school_id' => $schoolId,
                    'school_year_id' => $schoolYearId,
                    'classroom_id' => $classroomId,
                    'person_id' => $studentPersonId,
                    'student_number' => $studentNumber,
                    'status' => EnrollmentStatus::Active,
                    'enrolled_on' => now()->toDateString(),
                    'source_type' => 'native',
                ]);

                EnrollmentStatusChange::query()->create([
                    'school_id' => $schoolId,
                    'enrollment_id' => $enrollment->id,
                    'from_status' => null,
                    'to_status' => EnrollmentStatus::Active->value,
                    'reason' => 'inscription',
                    'occurred_at' => now(),
                    'actor_person_id' => $actorPersonId,
                ]);

                app(GrantSchoolPersonLink::class)->execute(
                    $schoolId,
                    $studentPersonId,
                    SchoolPersonLinkKind::Student,
                    SchoolPersonLinkSource::Enrollment,
                    grantsContactAccess: false,
                );

                Auditor::record('enrollment.activated', 'enrollment', $enrollment->id, $studentPersonId);
                TrustEvent::emit('person', $studentPersonId, 'enrollment.activated', $schoolId, 'enrollment', $enrollment->id);

                return $enrollment;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Cet élève a déjà une inscription active dans le réseau FANABE.', 409);
        }
    }

    private function validatedClassroomId(string $schoolId, string $schoolYearId, ?string $classroomId): ?string
    {
        if ($classroomId === null || $classroomId === '') {
            return null;
        }

        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        if ((string) $classroom->school_year_id !== $schoolYearId) {
            throw new DomainException('La classe n\'appartient pas à la même année scolaire.');
        }

        return $classroom->id;
    }
}
