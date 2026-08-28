<?php

namespace App\Domain\Platform\Demo;

use App\Domain\Academic\Enums\ClassActivityType;
use App\Domain\Academic\Enums\ClassCouncilStatus;
use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\ClassActivity;
use App\Domain\Academic\Models\ClassCouncil;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\ClassroomTeacher;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Enums\ExpenseCategory;
use App\Domain\Finance\Enums\ExpenseKind;
use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeItem;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Finance\Models\SchoolExpense;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolNetwork;
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
        $this->ensureNetwork($school);
        $sixieme = $this->ensureGrade($school->id, '6ème', GradeStage::Middle, 6);
        $cinquieme = $this->ensureGrade($school->id, '5ème', GradeStage::Middle, 5);
        $teacherId = $this->mainTeacherPersonId($school);
        $class6 = $this->ensureClassroom($year, $sixieme, '6ème A', $teacherId);
        $class5 = $this->ensureClassroom($year, $cinquieme, '5ème A', $teacherId);
        $this->ensureFeeSchedule($year);
        $this->assignEnrollments($year, $class6, $class5);
        $this->ensureClassLife($year, $class6, $teacherId);
        $this->ensurePreschoolDemo($school, $year, $teacherId);
        $this->ensureHighDemo($school, $year, $teacherId);
        $this->ensureExpense($year);
    }

    private function mainTeacherPersonId(School $school): ?string
    {
        if ($school->code !== 'antsahabe') {
            return null;
        }

        return UserAccount::query()
            ->whereRaw('lower(email) = ?', ['teacher.antsahabe@fanabe.test'])
            ->value('person_id');
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

    private function ensureNetwork(School $school): void
    {
        if (! in_array($school->code, ['antsahabe', 'ambohipo'], true)) {
            return;
        }

        $network = SchoolNetwork::query()->firstOrCreate(
            ['name' => 'Réseau Analakanga'],
        );

        if ((string) $school->network_id !== (string) $network->id) {
            $school->forceFill(['network_id' => $network->id])->save();
        }
    }

    private function ensureGrade(string $schoolId, string $name, GradeStage $stage, int $sequence): GradeLevel
    {
        return GradeLevel::query()->firstOrCreate(
            ['school_id' => $schoolId, 'name' => $name],
            ['stage' => $stage, 'sequence' => $sequence],
        );
    }

    private function ensureClassroom(SchoolYear $year, GradeLevel $grade, string $name, ?string $teacherPersonId, ?string $series = null): Classroom
    {
        $classroom = Classroom::query()->firstOrCreate(
            [
                'school_id' => $year->school_id,
                'school_year_id' => $year->id,
                'name' => $name,
            ],
            [
                'grade_level_id' => $grade->id,
                'capacity' => 40,
                'main_teacher_person_id' => $teacherPersonId,
                'series' => $series,
            ],
        );

        if ($teacherPersonId !== null && $classroom->main_teacher_person_id !== $teacherPersonId) {
            $classroom->forceFill(['main_teacher_person_id' => $teacherPersonId])->save();
        }

        if ($series !== null && $classroom->series !== $series) {
            $classroom->forceFill(['series' => $series])->save();
        }

        if ($teacherPersonId !== null) {
            ClassroomTeacher::query()->firstOrCreate(
                [
                    'school_id' => $year->school_id,
                    'classroom_id' => $classroom->id,
                    'person_id' => $teacherPersonId,
                ],
                ['subject' => null],
            );
        }

        return $classroom;
    }

    private function ensureClassLife(SchoolYear $year, Classroom $classroom, ?string $teacherPersonId): void
    {
        $term = AcademicTerm::query()
            ->where('school_year_id', $year->id)
            ->where('sequence', 1)
            ->first();

        $delegateId = Enrollment::query()
            ->where('classroom_id', $classroom->id)
            ->where('status', EnrollmentStatus::Active)
            ->orderBy('student_number')
            ->value('person_id');

        if ($delegateId !== null && $classroom->delegate_person_id === null) {
            $classroom->forceFill(['delegate_person_id' => $delegateId])->save();
        }

        if ($teacherPersonId !== null) {
            TimetableSlot::query()->firstOrCreate(
                [
                    'school_id' => $year->school_id,
                    'classroom_id' => $classroom->id,
                    'weekday' => 1,
                    'starts_at' => '07:30:00',
                ],
                [
                    'ends_at' => '08:25:00',
                    'subject' => 'Malagasy',
                    'teacher_person_id' => $teacherPersonId,
                    'room' => 'A1',
                ],
            );
            TimetableSlot::query()->firstOrCreate(
                [
                    'school_id' => $year->school_id,
                    'classroom_id' => $classroom->id,
                    'weekday' => 1,
                    'starts_at' => '08:30:00',
                ],
                [
                    'ends_at' => '09:25:00',
                    'subject' => 'Mathématiques',
                    'teacher_person_id' => $teacherPersonId,
                    'room' => 'A1',
                ],
            );
        }

        if ($term !== null) {
            ClassCouncil::query()->firstOrCreate(
                [
                    'school_id' => $year->school_id,
                    'classroom_id' => $classroom->id,
                    'academic_term_id' => $term->id,
                ],
                [
                    'held_on' => '2026-12-10',
                    'title' => 'Conseil du 1er trimestre',
                    'minutes' => 'Classe sérieuse. Travaux à poursuivre au 2e trimestre.',
                    'status' => ClassCouncilStatus::Held,
                ],
            );
        }

        ClassActivity::query()->firstOrCreate(
            [
                'school_id' => $year->school_id,
                'classroom_id' => $classroom->id,
                'title' => 'Réunion parents de rentrée',
            ],
            [
                'type' => ClassActivityType::ParentMeeting,
                'held_on' => '2026-09-12',
                'location' => 'Salle 6ème A',
                'notes' => 'Présentation de l’année et du professeur titulaire.',
            ],
        );
    }

    private function ensurePreschoolDemo(School $school, SchoolYear $year, ?string $teacherPersonId): void
    {
        if ($school->code !== 'antsahabe') {
            return;
        }

        $gs = $this->ensureGrade($year->school_id, 'GS', GradeStage::Preschool, 0);
        $classroom = $this->ensureClassroom($year, $gs, 'GS A', $teacherPersonId);

        ClassActivity::query()->firstOrCreate(
            [
                'school_id' => $year->school_id,
                'classroom_id' => $classroom->id,
                'title' => 'Accueil des parents',
            ],
            [
                'type' => ClassActivityType::ParentMeeting,
                'held_on' => '2026-09-08',
                'location' => 'Salle GS',
                'notes' => 'Présentation du groupe et du rythme de la journée.',
            ],
        );
    }

    private function ensureHighDemo(School $school, SchoolYear $year, ?string $teacherPersonId): void
    {
        if ($school->code !== 'antsahabe') {
            return;
        }

        $terminale = $this->ensureGrade($year->school_id, 'Terminale', GradeStage::High, 33);
        $this->ensureClassroom($year, $terminale, 'Tle S', $teacherPersonId, 'S');
    }

    private function ensureExpense(SchoolYear $year): void
    {
        $actorId = UserAccount::query()
            ->whereRaw('lower(email) = ?', ['direction.antsahabe@fanabe.test'])
            ->value('person_id');

        if ($actorId === null) {
            return;
        }

        SchoolExpense::query()->firstOrCreate(
            [
                'school_id' => $year->school_id,
                'school_year_id' => $year->id,
                'label' => 'Craie et cahiers de brouillon',
            ],
            [
                'kind' => ExpenseKind::Purchase,
                'category' => ExpenseCategory::Supplies,
                'amount' => 85_000,
                'spent_on' => '2026-09-05',
                'vendor' => 'Papeterie Analakely',
                'recorded_by_person_id' => $actorId,
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
                'status' => FeeScheduleStatus::Active,
                'submitted_at' => now(),
                'locked_at' => now(),
            ],
        );

        if ($schedule->status === FeeScheduleStatus::Active && $schedule->locked_at === null) {
            $schedule->forceFill([
                'submitted_at' => $schedule->submitted_at ?? $schedule->created_at,
                'locked_at' => $schedule->created_at,
            ])->save();
        }

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
