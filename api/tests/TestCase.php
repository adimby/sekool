<?php

namespace Tests;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Family\Models\Family;
use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Finance\Models\FeeItem;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Identity\Actions\CreateFamilyWithStudent;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolRoleAssignment;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * @return array{school: School, account: UserAccount, year: SchoolYear}
     */
    protected function provisionSchool(?SchoolRole $role = SchoolRole::Admin): array
    {
        TenantContext::activate(TenantContext::migrationBypass());

        $school = School::factory()->create();
        $account = UserAccount::factory()->create();

        SchoolRoleAssignment::query()->create([
            'school_id' => $school->id,
            'person_id' => $account->person_id,
            'role' => $role,
            'granted_at' => now(),
        ]);

        $year = SchoolYear::factory()->create([
            'school_id' => $school->id,
            'label' => '2026-2027',
        ]);

        TenantContext::clear();

        return compact('school', 'account', 'year');
    }

    /**
     * @param  array{school: School, account: UserAccount, year: SchoolYear}  $fixture
     * @param  array<string, mixed>  $parent
     * @param  array<string, mixed>  $student
     * @return array{
     *     parent: Person,
     *     student: Person,
     *     parentAccount: UserAccount,
     *     invitation_code: string,
     *     enrollment: Enrollment,
     *     family: Family
     * }
     */
    protected function provisionEnrolledFamily(array $fixture, array $parent = [], array $student = []): array
    {
        TenantContext::activate(TenantContext::forSchool($fixture['school']->id, $fixture['account']->person_id));

        $result = app(CreateFamilyWithStudent::class)->execute(
            schoolId: $fixture['school']->id,
            schoolYearId: $fixture['year']->id,
            actorPersonId: $fixture['account']->person_id,
            parent: array_merge([
                'first_name' => 'Mialy',
                'last_name' => 'Rakoto',
                'phone' => '034 12 345 67',
                'email' => 'mialy.'.uniqid().'@fanabe.test',
            ], $parent),
            student: array_merge([
                'first_name' => 'Fanja',
                'last_name' => 'Rakoto',
                'birth_date' => '2013-04-02',
            ], $student),
            relationship: RelationshipType::ParentOf,
        );

        $parentAccount = UserAccount::factory()->create([
            'person_id' => $result['parent']->id,
            'email' => $result['parent']->email ?? fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        TenantContext::clear();

        return [
            'parent' => $result['parent'],
            'student' => $result['student'],
            'parentAccount' => $parentAccount,
            'invitation_code' => $result['invitation_code'],
            'enrollment' => $result['enrollment'],
            'family' => $result['family'],
        ];
    }

    protected function actingAsSchool(UserAccount $account, School $school): static
    {
        TenantContext::activate(TenantContext::forSchool($school->id, $account->person_id));

        return $this->actingAs($account);
    }

    /**
     * @param  array{school: School, account: UserAccount, year: SchoolYear}  $fixture
     * @return array{grade: GradeLevel, schedule: FeeSchedule}
     */
    protected function provisionFeeSchedule(array $fixture, int $itemAmount = 50_000): array
    {
        TenantContext::activate(TenantContext::forSchool($fixture['school']->id, $fixture['account']->person_id));

        $grade = GradeLevel::query()->create([
            'school_id' => $fixture['school']->id,
            'name' => '6ème',
            'stage' => GradeStage::Middle,
            'sequence' => 6,
        ]);

        $schedule = FeeSchedule::query()->create([
            'school_id' => $fixture['school']->id,
            'school_year_id' => $fixture['year']->id,
            'grade_level_id' => null,
            'name' => 'Écolage 2026-2027',
            'status' => 'active',
            'submitted_at' => now(),
            'locked_at' => now(),
        ]);

        foreach ([
            ['code' => 'SCOL_T1', 'label' => 'Écolage 1er trimestre', 'due_on' => '2026-09-15'],
            ['code' => 'SCOL_T2', 'label' => 'Écolage 2e trimestre', 'due_on' => '2027-01-15'],
            ['code' => 'SCOL_T3', 'label' => 'Écolage 3e trimestre', 'due_on' => '2027-04-15'],
        ] as $item) {
            FeeItem::query()->create([
                'school_id' => $fixture['school']->id,
                'fee_schedule_id' => $schedule->id,
                'code' => $item['code'],
                'label' => $item['label'],
                'amount' => $itemAmount,
                'due_on' => $item['due_on'],
                'category' => FeeCategory::Tuition,
                'is_recurring' => false,
            ]);
        }

        TenantContext::clear();

        return compact('grade', 'schedule');
    }

    /**
     * @param  array{school: School, account: UserAccount, year: SchoolYear}  $fixture
     * @return array{account: UserAccount}
     */
    protected function provisionTeacher(array $fixture, ?string $classroomId = null): array
    {
        TenantContext::activate(TenantContext::forSchool($fixture['school']->id, $fixture['account']->person_id));

        $account = UserAccount::factory()->create();

        SchoolRoleAssignment::query()->create([
            'school_id' => $fixture['school']->id,
            'person_id' => $account->person_id,
            'role' => SchoolRole::Teacher,
            'granted_at' => now(),
        ]);

        if ($classroomId !== null) {
            Classroom::query()->whereKey($classroomId)->update([
                'main_teacher_person_id' => $account->person_id,
            ]);
        }

        TenantContext::clear();

        return compact('account');
    }
}
