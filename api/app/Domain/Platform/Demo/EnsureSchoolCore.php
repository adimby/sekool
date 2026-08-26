<?php

namespace App\Domain\Platform\Demo;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Finance\Models\FeeItem;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolYear;

final class EnsureSchoolCore
{
    public function execute(): void
    {
        TenantContext::runWithRlsBypass(function (): void {
            foreach (School::query()->get() as $school) {
                $this->ensureFor($school);
            }
        });
    }

    private function ensureFor(School $school): void
    {
        $year = SchoolYear::query()
            ->withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('is_current', true)
            ->first();

        if ($year === null) {
            $year = SchoolYear::query()->create([
                'school_id' => $school->id,
                'label' => '2026-2027',
                'starts_on' => '2026-09-01',
                'ends_on' => '2027-07-15',
                'is_current' => true,
            ]);
        }

        $this->ensureTerms($year);
        $sixieme = $this->ensureGrade($school->id, '6ème', GradeStage::Middle, 6);
        $cinquieme = $this->ensureGrade($school->id, '5ème', GradeStage::Middle, 5);
        $class6 = $this->ensureClassroom($year, $sixieme, '6ème A');
        $class5 = $this->ensureClassroom($year, $cinquieme, '5ème A');
        $this->ensureFeeSchedule($year);
        $this->assignEnrollments($year, $class6, $class5);
    }

    private function ensureTerms(SchoolYear $year): void
    {
        $terms = [
            ['label' => '1er trimestre', 'sequence' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-18'],
            ['label' => '2e trimestre', 'sequence' => 2, 'starts_on' => '2027-01-05', 'ends_on' => '2027-03-31'],
            ['label' => '3e trimestre', 'sequence' => 3, 'starts_on' => '2027-04-01', 'ends_on' => '2027-07-15'],
        ];

        foreach ($terms as $term) {
            AcademicTerm::query()->firstOrCreate(
                [
                    'school_id' => $year->school_id,
                    'school_year_id' => $year->id,
                    'sequence' => $term['sequence'],
                ],
                [
                    'label' => $term['label'],
                    'starts_on' => $term['starts_on'],
                    'ends_on' => $term['ends_on'],
                ],
            );
        }
    }

    private function ensureGrade(string $schoolId, string $name, GradeStage $stage, int $sequence): GradeLevel
    {
        return GradeLevel::query()->firstOrCreate(
            ['school_id' => $schoolId, 'name' => $name],
            ['stage' => $stage, 'sequence' => $sequence],
        );
    }

    private function ensureClassroom(SchoolYear $year, GradeLevel $grade, string $name): Classroom
    {
        return Classroom::query()->firstOrCreate(
            [
                'school_id' => $year->school_id,
                'school_year_id' => $year->id,
                'name' => $name,
            ],
            [
                'grade_level_id' => $grade->id,
                'capacity' => 40,
            ],
        );
    }

    private function ensureFeeSchedule(SchoolYear $year): void
    {
        $schedule = FeeSchedule::query()->firstOrCreate(
            [
                'school_id' => $year->school_id,
                'school_year_id' => $year->id,
                'name' => 'Écolage 2026-2027',
            ],
            [
                'grade_level_id' => null,
                'status' => 'active',
            ],
        );

        $items = [
            ['code' => 'SCOL_T1', 'label' => 'Écolage 1er trimestre', 'due_on' => '2026-09-15'],
            ['code' => 'SCOL_T2', 'label' => 'Écolage 2e trimestre', 'due_on' => '2027-01-15'],
            ['code' => 'SCOL_T3', 'label' => 'Écolage 3e trimestre', 'due_on' => '2027-04-15'],
        ];

        foreach ($items as $item) {
            FeeItem::query()->firstOrCreate(
                [
                    'school_id' => $year->school_id,
                    'fee_schedule_id' => $schedule->id,
                    'code' => $item['code'],
                ],
                [
                    'label' => $item['label'],
                    'amount' => 50_000,
                    'due_on' => $item['due_on'],
                    'category' => FeeCategory::Tuition,
                    'is_recurring' => false,
                ],
            );
        }
    }

    private function assignEnrollments(SchoolYear $year, Classroom $class6, Classroom $class5): void
    {
        $enrollments = Enrollment::query()
            ->withoutGlobalScopes()
            ->with('person')
            ->where('school_id', $year->school_id)
            ->where('school_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active)
            ->whereNull('classroom_id')
            ->get();

        foreach ($enrollments as $enrollment) {
            $fifth = str_contains((string) $enrollment->student_number, '5E')
                || ($enrollment->person?->first_name === 'Fanja');

            $enrollment->classroom_id = $fifth ? $class5->id : $class6->id;
            $enrollment->save();
        }
    }
}
