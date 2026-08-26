<?php

use App\Domain\Academic\Models\Classroom;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\School;

it('serves a placeholder when the built UI is absent', function () {
    $this->get('/')->assertStatus(503);
});

it('bootstraps demo data only once', function () {
    $this->artisan('demo:bootstrap')->assertSuccessful();
    $count = School::query()->count();
    expect($count)->toBeGreaterThan(0);

    $this->artisan('demo:bootstrap')->assertSuccessful();
    expect(School::query()->count())->toBe($count);
});

it('seeds demo users when schools already exist without accounts', function () {
    TenantContext::activate(TenantContext::migrationBypass());
    School::factory()->create(['code' => 'ghost-school']);
    TenantContext::clear();

    expect(UserAccount::query()->where('email', 'direction.antsahabe@fanabe.test')->exists())->toBeFalse();

    $this->artisan('demo:bootstrap')->assertSuccessful();

    expect(UserAccount::query()->where('email', 'direction.antsahabe@fanabe.test')->exists())->toBeTrue();
});

it('lets the Antsahabe direction account log in after bootstrap', function () {
    $this->artisan('demo:bootstrap')->assertSuccessful();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'direction.antsahabe@fanabe.test',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonStructure(['token', 'person_id', 'person', 'schools', 'is_parent', 'is_student'])
        ->assertJsonPath('schools.0.code', 'antsahabe')
        ->assertJsonPath('schools.0.role', 'school_admin')
        ->assertJsonPath('is_student', false);
});

it('lets the Antsahabe teacher take attendance and forbids the direction from doing so', function () {
    $this->artisan('demo:bootstrap')->assertSuccessful();

    $teacher = $this->postJson('/api/v1/auth/login', [
        'email' => 'teacher.antsahabe@fanabe.test',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('schools.0.role', 'teacher')
        ->assertJsonPath('is_student', false);

    $schoolId = $teacher->json('schools.0.id');
    $token = $teacher->json('token');

    $classrooms = $this->withToken($token)
        ->getJson("/api/v1/schools/{$schoolId}/classrooms")
        ->assertOk()
        ->json('data');

    expect($classrooms)->not->toBeEmpty();

    $classroomId = $classrooms[0]['id'];
    $roster = $this->withToken($token)
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroomId}/roster")
        ->assertOk()
        ->json('data.students');

    expect($roster)->not->toBeEmpty();

    $this->withToken($token)
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => '2026-09-15',
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $roster[0]['enrollment_id'],
                'status' => 'present',
                'client_reference' => '33333333-3333-4333-8333-333333333333',
            ]],
        ])
        ->assertCreated();

    $this->flushHeaders();

    $directionAccount = UserAccount::query()
        ->whereRaw('lower(email) = ?', ['direction.antsahabe@fanabe.test'])
        ->firstOrFail();

    $this->actingAs($directionAccount, 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => '2026-09-15',
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $roster[0]['enrollment_id'],
                'status' => 'absent',
            ]],
        ])
        ->assertForbidden();
});

it('lets Fanja open a read-only student space after bootstrap', function () {
    $this->artisan('demo:bootstrap')->assertSuccessful();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'eleve.fanja@fanabe.test',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('is_student', true)
        ->assertJsonPath('is_parent', false)
        ->assertJsonCount(0, 'schools');

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'eleve.fanja@fanabe.test',
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('token'))
        ->getJson('/api/v1/student/me')
        ->assertOk()
        ->assertJsonPath('person.first_name', 'Fanja')
        ->assertJsonPath('enrollment.classroom.name', '5ème A');
});

it('seeds classrooms and fee schedules for demo schools', function () {
    $this->artisan('demo:bootstrap')->assertSuccessful();

    TenantContext::runWithRlsBypass(function (): void {
        expect(Classroom::query()->count())->toBeGreaterThan(0)
            ->and(FeeSchedule::query()->count())->toBe(3);
    });
});

it('fills the Antsahabe cockpit with three priority actions after bootstrap', function () {
    $this->artisan('demo:bootstrap')->assertSuccessful();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'direction.antsahabe@fanabe.test',
        'password' => 'password',
    ])->assertOk();

    $schoolId = $login->json('schools.0.id');

    $this->flushHeaders();
    $this->actingAs(
        \App\Domain\Identity\Models\UserAccount::query()
            ->whereRaw('lower(email) = ?', ['direction.antsahabe@fanabe.test'])
            ->firstOrFail(),
        'sanctum',
    )->getJson("/api/v1/schools/{$schoolId}/cockpit")
        ->assertOk()
        ->assertJsonCount(3, 'actions');
});
