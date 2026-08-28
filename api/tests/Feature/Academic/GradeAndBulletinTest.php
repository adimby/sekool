<?php

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;

it('records grades, builds a bulletin, and hides notes from preschool and financial contacts', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $subjectId = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/subjects", ['name' => 'Mathématiques'])
        ->assertCreated()
        ->json('data.id');

    $teacher = $this->provisionTeacher($school, $classroom['id']);

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/grades", [
            'enrollment_id' => $family['enrollment']->id,
            'subject_id' => $subjectId,
            'value' => 12,
            'max_value' => 20,
            'coefficient' => 2,
            'assessed_on' => '2026-10-02',
        ])
        ->assertCreated();

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/grades", [
            'enrollment_id' => $family['enrollment']->id,
            'subject_id' => $subjectId,
            'value' => 16,
            'max_value' => 20,
            'coefficient' => 1,
            'assessed_on' => '2026-10-16',
        ])
        ->assertCreated();

    $bulletin = $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/bulletin")
        ->assertOk()
        ->json('data');

    expect($bulletin['overall_average'])->toBe(13.33);

    $adult = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/families/{$family['family']->id}/adults", [
            'first_name' => 'Soa',
            'last_name' => 'Andria',
            'email' => 'soa.'.uniqid().'@fanabe.test',
            'relationship' => RelationshipType::FinancialContactFor->value,
        ])
        ->assertSuccessful()
        ->json('adult');

    $financeAccount = UserAccount::factory()->create([
        'person_id' => $adult['id'],
        'email' => 'finance.'.uniqid().'@fanabe.test',
        'password' => 'password',
    ]);

    $this->actingAs($financeAccount, 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/bulletin")
        ->assertNotFound();

    $this->actingAs($financeAccount, 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/certificates")
        ->assertNotFound();

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    $gs = GradeLevel::query()->create([
        'school_id' => $schoolId,
        'name' => 'GS',
        'stage' => GradeStage::Preschool,
        'sequence' => 0,
    ]);
    $group = Classroom::query()->create([
        'school_id' => $schoolId,
        'school_year_id' => $school['year']->id,
        'grade_level_id' => $gs->id,
        'name' => 'GS A',
        'capacity' => 20,
    ]);
    TenantContext::clear();

    $preschool = $this->provisionEnrolledFamily($school, student: ['first_name' => 'Naina', 'last_name' => 'Rasoa']);
    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$preschool['enrollment']->id}/assign-classroom", [
            'classroom_id' => $group->id,
        ]);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$group->id}/grades", [
            'enrollment_id' => $preschool['enrollment']->id,
            'subject_id' => $subjectId,
            'value' => 10,
            'assessed_on' => '2026-10-02',
        ])
        ->assertStatus(422);

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$group->id}/grades")
        ->assertOk()
        ->assertJsonPath('grades_enabled', false)
        ->assertJsonCount(0, 'data');
});
