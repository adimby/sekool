<?php

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Identity\Support\Totp;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\SchoolRoleAssignment;

it('lets a teacher sign in without TOTP and requires TOTP for direction', function () {
    $school = $this->provisionSchool();
    $teacher = $this->provisionTeacher($school);

    $this->postJson('/api/v1/auth/login', [
        'email' => $teacher['account']->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('schools.0.role', 'teacher')
        ->assertJsonMissingPath('challenge');

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => $school['account']->email,
        'password' => 'password',
    ])->assertOk()
        ->json();

    expect($challenge['challenge'])->toBe('totp_enroll')
        ->and($challenge)->not->toHaveKey('token')
        ->and($challenge['secret'])->not->toBeEmpty()
        ->and($challenge['demo_code'])->toHaveLength(6);

    $this->postJson('/api/v1/auth/totp', [
        'challenge_id' => $challenge['challenge_id'],
        'code' => '000000',
    ])->assertUnprocessable();

    $session = $this->postJson('/api/v1/auth/totp', [
        'challenge_id' => $challenge['challenge_id'],
        'code' => Totp::code($challenge['secret']),
    ])->assertOk()
        ->assertJsonStructure(['token', 'person_id', 'schools']);

    $again = $this->postJson('/api/v1/auth/login', [
        'email' => $school['account']->email,
        'password' => 'password',
    ])->assertOk()
        ->json();

    expect($again['challenge'])->toBe('totp')
        ->and($again)->not->toHaveKey('secret');

    $this->postJson('/api/v1/auth/totp', [
        'challenge_id' => $again['challenge_id'],
        'code' => $again['demo_code'],
    ])->assertOk()
        ->assertJsonPath('person_id', $school['account']->person_id);

    expect($session['token'])->not->toBeEmpty();
});

it('requires TOTP for an accountant and never for a parent', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);

    TenantContext::activate(TenantContext::forSchool($school['school']->id, $school['account']->person_id));
    $accountant = UserAccount::factory()->create();
    SchoolRoleAssignment::query()->create([
        'school_id' => $school['school']->id,
        'person_id' => $accountant->person_id,
        'role' => SchoolRole::Accountant,
        'granted_at' => now(),
    ]);
    TenantContext::clear();

    $this->postJson('/api/v1/auth/login', [
        'email' => $accountant->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('challenge', 'totp_enroll');

    $this->postJson('/api/v1/auth/login', [
        'email' => $family['parentAccount']->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonMissingPath('challenge')
        ->assertJsonPath('is_parent', true);
});

it('requires TOTP for a platform admin without a school', function () {
    $account = $this->provisionPlatformAdmin();

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => $account->email,
        'password' => 'password',
    ])->assertOk()
        ->json();

    expect($challenge['challenge'])->toBe('totp_enroll')
        ->and($challenge)->not->toHaveKey('token');

    $session = $this->postJson('/api/v1/auth/totp', [
        'challenge_id' => $challenge['challenge_id'],
        'code' => $challenge['demo_code'],
    ])->assertOk()
        ->assertJsonPath('is_platform_admin', true)
        ->assertJsonCount(0, 'schools');

    expect($session['token'])->not->toBeEmpty();
});
