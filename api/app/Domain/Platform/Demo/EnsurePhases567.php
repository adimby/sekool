<?php

namespace App\Domain\Platform\Demo;

use App\Domain\Academic\Actions\DetectStudentAlerts;
use App\Domain\Academic\Actions\RecordGrade;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\GradeEntry;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Certificate\Actions\IssueEnrollmentCertificate;
use App\Domain\Certificate\Models\Certificate;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\School;
use App\Domain\SchoolKit\Actions\SaveKitCatalog;
use App\Domain\SchoolKit\Models\KitDefinition;

final class EnsurePhases567
{
    public function execute(): void
    {
        TenantContext::runWithRlsBypass(function (): void {
            $school = School::query()->where('code', 'antsahabe')->first();
            if ($school === null) {
                return;
            }

            TenantContext::run(
                TenantContext::forSchool((string) $school->id),
                fn () => $this->seedAntsahabe($school),
            );
        });
    }

    private function seedAntsahabe(School $school): void
    {
        $actorId = UserAccount::query()
            ->whereRaw('lower(email) = ?', ['direction.antsahabe@fanabe.test'])
            ->value('person_id');
        $teacherId = UserAccount::query()
            ->whereRaw('lower(email) = ?', ['teacher.antsahabe@fanabe.test'])
            ->value('person_id') ?? $actorId;

        if ($actorId === null) {
            return;
        }

        $year = $school->years()->where('is_current', true)->first()
            ?? $school->years()->first();
        if ($year === null) {
            return;
        }

        $sixieme = GradeLevel::query()
            ->where('school_id', $school->id)
            ->where('name', '6ème')
            ->first();
        $class6 = Classroom::query()
            ->where('school_id', $school->id)
            ->where('name', '6ème A')
            ->first();

        $malagasy = Subject::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Malagasy'],
        );
        $maths = Subject::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Mathématiques'],
        );

        $term = AcademicTerm::query()
            ->where('school_year_id', $year->id)
            ->where('sequence', 1)
            ->first();

        $hery = Enrollment::query()
            ->with('person')
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('person', fn ($query) => $query->where('first_name', 'Hery'))
            ->first();

        if ($hery !== null && $class6 !== null && $hery->classroom_id === null) {
            $hery->forceFill(['classroom_id' => $class6->id])->save();
        }

        if ($hery !== null && $teacherId !== null) {
            $grades = app(RecordGrade::class);
            if (! GradeEntry::query()->where('enrollment_id', $hery->id)->exists()) {
                $grades->execute((string) $school->id, $teacherId, [
                    'enrollment_id' => $hery->id,
                    'subject_id' => $malagasy->id,
                    'academic_term_id' => $term?->id,
                    'value' => 14,
                    'max_value' => 20,
                    'coefficient' => 1,
                    'assessed_on' => '2026-10-06',
                ]);
                $grades->execute((string) $school->id, $teacherId, [
                    'enrollment_id' => $hery->id,
                    'subject_id' => $maths->id,
                    'academic_term_id' => $term?->id,
                    'value' => 11,
                    'max_value' => 20,
                    'coefficient' => 2,
                    'assessed_on' => '2026-10-08',
                ]);
            }

            if (! Certificate::query()->where('enrollment_id', $hery->id)->exists()) {
                app(IssueEnrollmentCertificate::class)->execute((string) $school->id, (string) $hery->id, $actorId);
            }
        }

        if ($sixieme !== null && ! KitDefinition::query()->where('name', 'Kit 6ème')->exists()) {
            app(SaveKitCatalog::class)->execute((string) $school->id, [
                'school_year_id' => $year->id,
                'grade_level_id' => $sixieme->id,
                'name' => 'Kit 6ème',
                'needs' => [
                    ['label' => 'Cahier 200 pages', 'quantity' => 4],
                    ['label' => 'Stylos', 'quantity' => 6],
                ],
                'supplier_name' => 'Librairie Analakely',
                'supplier_contact' => 'Analakely',
                'commission_rate_bps' => 250,
                'packs' => [
                    ['tier' => 'eco', 'total_amount' => 45_000],
                    ['tier' => 'standard', 'total_amount' => 72_000],
                    ['tier' => 'premium', 'total_amount' => 98_000],
                ],
            ]);
        }

        app(DetectStudentAlerts::class)->execute();
    }
}
