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
use App\Domain\School\Models\SchoolYear;
use App\Domain\SchoolKit\Actions\SaveKitCatalog;

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

        if ($sixieme !== null) {
            $this->seedSupplyList($school, $year, $sixieme, 'Fournitures 6ème', [
                ['label' => 'Cahier 200 pages', 'quantity' => 4, 'eco' => ['Oxford', 4_000], 'standard' => ['Clairefontaine', 6_500], 'luxe' => ['Rhodia', 9_000]],
                ['label' => 'Stylos', 'quantity' => 6, 'eco' => ['BIC', 1_500], 'standard' => ['Schneider', 2_500], 'luxe' => ['Pilot', 3_500]],
                ['label' => 'Classeur', 'quantity' => 2, 'eco' => ['Générique', 10_000], 'standard' => ['Exacompta', 15_500], 'luxe' => ['Leitz', 20_500]],
            ]);
        }

        $cinquieme = GradeLevel::query()
            ->where('school_id', $school->id)
            ->where('name', '5ème')
            ->first();
        if ($cinquieme !== null) {
            $this->seedSupplyList($school, $year, $cinquieme, 'Fournitures 5ème', [
                ['label' => 'Cahier 200 pages', 'quantity' => 6, 'eco' => ['Oxford', 4_000], 'standard' => ['Clairefontaine', 6_500], 'luxe' => ['Rhodia', 9_000]],
                ['label' => 'Stylos', 'quantity' => 8, 'eco' => ['BIC', 1_500], 'standard' => ['Schneider', 2_500], 'luxe' => ['Pilot', 3_500]],
                ['label' => 'Compas', 'quantity' => 1, 'eco' => ['Maped', 8_000], 'standard' => ['Staedtler', 14_000], 'luxe' => ['Rotring', 22_000]],
            ]);
        }

        app(DetectStudentAlerts::class)->execute();
    }

    /**
     * @param  list<array{label: string, quantity: int, eco: array{0: string, 1: int}, standard: array{0: string, 1: int}, luxe: array{0: string, 1: int}}>  $articles
     */
    private function seedSupplyList(School $school, SchoolYear $year, GradeLevel $grade, string $name, array $articles): void
    {
        app(SaveKitCatalog::class)->execute((string) $school->id, [
            'school_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'name' => $name,
            'price_source' => 'supplier',
            'needs' => array_map(fn (array $article): array => [
                'label' => $article['label'],
                'quantity' => $article['quantity'],
                'offers' => [
                    ['tier' => 'eco', 'brand' => $article['eco'][0], 'unit_amount' => $article['eco'][1]],
                    ['tier' => 'standard', 'brand' => $article['standard'][0], 'unit_amount' => $article['standard'][1]],
                    ['tier' => 'luxe', 'brand' => $article['luxe'][0], 'unit_amount' => $article['luxe'][1]],
                ],
            ], $articles),
            'supplier_name' => 'Librairie Analakely',
            'supplier_contact' => 'Analakely',
            'commission_rate_bps' => 250,
        ]);
    }
}
