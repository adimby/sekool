<?php

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;

it('forbids the direction from taking attendance', function () {
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

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => '2026-09-15',
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])
        ->assertForbidden();
});

it('lets the class teacher take attendance and hides school-wide directories', function () {
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

    $other = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème B',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $teacher = $this->provisionTeacher($school, $classroom['id']);

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => '2026-09-15',
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.status', 'present');

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/attendance?".http_build_query([
            'classroom_id' => $classroom['id'],
            'date' => '2026-09-15',
            'session' => 'full_day',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.attendance.status', 'present');

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $classroom['id']);

    expect($this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/roster")
        ->json('data.students.0'))->not->toHaveKey('invoice');

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/attendance?".http_build_query([
            'classroom_id' => $other['id'],
            'date' => '2026-09-15',
            'session' => 'full_day',
        ]))
        ->assertNotFound();

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/people")
        ->assertForbidden();

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/enrollments")
        ->assertForbidden();

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->assertForbidden();
});

it('forbids a teacher from taking attendance in another classroom', function () {
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

    $stranger = $this->provisionTeacher($school);

    $this->actingAs($stranger['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => '2026-09-15',
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'absent',
            ]],
        ])
        ->assertForbidden();
});

it('lets a student read their own schooling and hides it from a parent', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '5ème A',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->assertCreated();

    $studentAccount = null;
    TenantContext::runWithRlsBypass(function () use ($family, &$studentAccount): void {
        $studentAccount = UserAccount::factory()->create([
            'person_id' => $family['student']->id,
            'email' => 'eleve.'.uniqid().'@fanabe.test',
        ]);
    });

    $this->actingAs($studentAccount, 'sanctum')
        ->getJson('/api/v1/student/me')
        ->assertOk()
        ->assertJsonPath('person.id', $family['student']->id)
        ->assertJsonPath('enrollment.classroom.id', $classroom['id'])
        ->assertJsonPath('finance.invoice.net_amount', 150_000);

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/student/me')
        ->assertNotFound();

    $this->actingAs($school['account'], 'sanctum')
        ->getJson('/api/v1/student/me')
        ->assertNotFound();
});

it('exposes distinct session flags for teacher and student', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $teacher = $this->provisionTeacher($school);

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('schools.0.role', 'teacher')
        ->assertJsonPath('schools.0.roles.0', 'teacher')
        ->assertJsonPath('is_student', false);

    $studentAccount = TenantContext::runWithRlsBypass(fn () => UserAccount::factory()->create([
        'person_id' => $family['student']->id,
        'email' => 'eleve.'.uniqid().'@fanabe.test',
    ]));

    $this->actingAs($studentAccount, 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('is_student', true)
        ->assertJsonPath('is_parent', false)
        ->assertJsonCount(0, 'schools');
});
