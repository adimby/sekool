<?php

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\SchoolRoleAssignment;

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

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('schools.0.titulaire', true);

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
        ->assertJsonPath('schools.0.titulaire', false)
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

function assignToSchool(array $host, UserAccount $account, SchoolRole $role): void
{
    TenantContext::activate(TenantContext::forSchool($host['school']->id, $host['account']->person_id));
    SchoolRoleAssignment::query()->create([
        'school_id' => $host['school']->id,
        'person_id' => $account->person_id,
        'role' => $role,
        'granted_at' => now(),
    ]);
    TenantContext::clear();
}

it('exposes session capabilities that match menus, not leftover tabs', function () {
    $school = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $titulaire = $this->provisionTeacher($school, $classroom['id']);
    $maths = $this->provisionTeacher($school);
    $accountantHost = $this->provisionSchool(SchoolRole::Accountant);
    $principalHost = $this->provisionSchool(SchoolRole::Principal);
    assignToSchool($school, $accountantHost['account'], SchoolRole::Accountant);
    assignToSchool($school, $principalHost['account'], SchoolRole::Principal);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/teachers", [
            'person_id' => $maths['account']->person_id,
            'subject' => 'Mathématiques',
        ])
        ->assertCreated();

    $this->actingAs($school['account'], 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('schools.0.capabilities.accueil', true)
        ->assertJsonPath('schools.0.capabilities.caisse', true)
        ->assertJsonPath('schools.0.capabilities.vie', false);

    $principalSchools = $this->actingAs($principalHost['account'], 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->json('schools');
    $principalHere = collect($principalSchools)->firstWhere('id', $schoolId);
    expect($principalHere['capabilities']['accueil'])->toBeTrue()
        ->and($principalHere['capabilities']['finance'])->toBeTrue()
        ->and($principalHere['capabilities']['caisse'])->toBeFalse();

    $accountantSchools = $this->actingAs($accountantHost['account'], 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->json('schools');
    $accountantHere = collect($accountantSchools)->firstWhere('id', $schoolId);
    expect($accountantHere['capabilities']['accueil'])->toBeFalse()
        ->and($accountantHere['capabilities']['famille'])->toBeFalse()
        ->and($accountantHere['capabilities']['finance'])->toBeTrue()
        ->and($accountantHere['capabilities']['caisse'])->toBeTrue()
        ->and($accountantHere['capabilities']['kits'])->toBeTrue()
        ->and($accountantHere['capabilities']['indices'])->toBeFalse();

    $this->actingAs($titulaire['account'], 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('schools.0.titulaire', true)
        ->assertJsonPath('schools.0.capabilities.vie', true)
        ->assertJsonPath('schools.0.capabilities.notes', false)
        ->assertJsonPath('schools.0.capabilities.kits', true);

    $this->actingAs($maths['account'], 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('schools.0.titulaire', false)
        ->assertJsonPath('schools.0.enseigne', true)
        ->assertJsonPath('schools.0.capabilities.appel', true)
        ->assertJsonPath('schools.0.capabilities.vie', false)
        ->assertJsonPath('schools.0.capabilities.notes', true)
        ->assertJsonPath('schools.0.capabilities.kits', false);
});

it('lets the accountant collect fees and hides pupil directories, while the principal cannot write the till', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;
    $accountantHost = $this->provisionSchool(SchoolRole::Accountant);
    $principalHost = $this->provisionSchool(SchoolRole::Principal);
    assignToSchool($school, $accountantHost['account'], SchoolRole::Accountant);
    assignToSchool($school, $principalHost['account'], SchoolRole::Principal);

    $invoice = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->assertCreated()
        ->json('data');

    $this->actingAs($principalHost['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/enrollments")
        ->assertOk();

    $this->actingAs($principalHost['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/payments", [
            'invoice_id' => $invoice['id'],
            'amount' => 10_000,
            'method' => 'cash',
            'received_on' => '2026-09-10',
        ])
        ->assertForbidden();

    $this->actingAs($principalHost['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->assertForbidden();

    $this->actingAs($accountantHost['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/people")
        ->assertForbidden();

    $this->actingAs($accountantHost['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/cockpit")
        ->assertForbidden();

    $this->actingAs($accountantHost['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/enrollments")
        ->assertOk();

    $this->actingAs($accountantHost['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/collection/queue")
        ->assertOk();

    $this->actingAs($accountantHost['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms")
        ->assertOk();

    $this->actingAs($accountantHost['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/payments", [
            'invoice_id' => $invoice['id'],
            'amount' => 10_000,
            'method' => 'cash',
            'received_on' => '2026-09-10',
        ])
        ->assertCreated();
});

it('lets a subject teacher list the class and record grades without opening the class file', function () {
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

    $maths = $this->provisionTeacher($school);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/teachers", [
            'person_id' => $maths['account']->person_id,
            'subject' => 'Mathématiques',
        ])
        ->assertCreated();

    $subjectId = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/subjects", ['name' => 'Mathématiques'])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($maths['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms")
        ->assertOk()
        ->assertJsonPath('data.0.id', $classroom['id']);

    $this->actingAs($maths['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}")
        ->assertNotFound();

    $this->actingAs($maths['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/roster")
        ->assertOk()
        ->assertJsonPath('data.students.0.enrollment_id', $family['enrollment']->id);

    $this->actingAs($maths['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/grades", [
            'enrollment_id' => $family['enrollment']->id,
            'subject_id' => $subjectId,
            'value' => 14,
            'assessed_on' => '2026-10-02',
        ])
        ->assertCreated();
});
