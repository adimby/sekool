<?php

use App\Domain\Platform\Audit\AuditEvent;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolRoleAssignment;
use App\Domain\School\Models\SchoolYear;

it('lets platform admin create a school with a first direction account and no finance fields', function () {
    $platform = $this->provisionPlatformAdmin();

    $created = $this->actingAs($platform, 'sanctum')
        ->postJson('/api/v1/platform/schools', [
            'name' => 'École Ankadifotsy',
            'code' => 'ankadifotsy',
            'city' => 'Antananarivo',
            'region' => 'Analamanga',
            'plan' => 'starter',
            'admin' => [
                'first_name' => 'Miora',
                'last_name' => 'Rabe',
                'email' => 'direction.ankadifotsy@fanabe.test',
            ],
        ])
        ->assertCreated()
        ->json('data');

    expect($created)
        ->toHaveKeys(['id', 'name', 'code', 'city', 'plan', 'status', 'admins', 'temporary_password'])
        ->and($created['name'])->toBe('École Ankadifotsy')
        ->and($created['code'])->toBe('ankadifotsy')
        ->and($created['status'])->toBe('active')
        ->and($created['plan'])->toBe('starter')
        ->and($created['temporary_password'])->not->toBeEmpty()
        ->and($created)->not->toHaveKey('settings')
        ->and($created)->not->toHaveKey('currency')
        ->and($created)->not->toHaveKeys(['invoices', 'payments', 'remaining_amount', 'headcount', 'enrollments']);

    expect($created['admins'])->toHaveCount(1)
        ->and($created['admins'][0]['email'])->toBe('direction.ankadifotsy@fanabe.test')
        ->and($created['admins'][0]['role'])->toBe(SchoolRole::Admin->value);

    $school = School::query()->findOrFail($created['id']);
    TenantContext::runWithRlsBypass(function () use ($school): void {
        expect(SchoolYear::query()->withoutGlobalScopes()->where('school_id', $school->id)->where('is_current', true)->exists())->toBeTrue();
    });

    expect(TenantContext::runWithRlsBypass(
        fn () => AuditEvent::query()->where('action', 'platform.school.created')->where('resource_id', $school->id)->exists(),
    ))->toBeTrue();

    $this->loginJson('direction.ankadifotsy@fanabe.test', $created['temporary_password'])
        ->assertJsonPath('schools.0.code', 'ankadifotsy')
        ->assertJsonPath('schools.0.role', 'school_admin')
        ->assertJsonPath('is_platform_admin', false);
});

it('requires a logged reason to change status or plan, not for a city update', function () {
    $platform = $this->provisionPlatformAdmin();
    $school = $this->provisionSchool()['school'];

    $this->actingAs($platform, 'sanctum')
        ->patchJson("/api/v1/platform/schools/{$school->id}", [
            'plan' => 'plus',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $this->actingAs($platform, 'sanctum')
        ->patchJson("/api/v1/platform/schools/{$school->id}", [
            'city' => 'Toamasina',
        ])
        ->assertOk()
        ->assertJsonPath('data.city', 'Toamasina')
        ->assertJsonPath('data.plan', $school->plan);

    $this->actingAs($platform, 'sanctum')
        ->patchJson("/api/v1/platform/schools/{$school->id}", [
            'plan' => 'plus',
            'reason' => 'Demande écrite de la direction, avenant d’abonnement.',
        ])
        ->assertOk()
        ->assertJsonPath('data.plan', 'plus');

    expect(TenantContext::runWithRlsBypass(fn () => AuditEvent::query()
        ->where('action', 'platform.school.updated')
        ->where('resource_id', $school->id)
        ->where('context->reason', 'Demande écrite de la direction, avenant d’abonnement.')
        ->exists()))->toBeTrue();
});

it('lists directory schools for platform admin and hides the route from a school admin', function () {
    $fixture = $this->provisionSchool();
    $platform = $this->provisionPlatformAdmin();

    $listed = $this->actingAs($platform, 'sanctum')
        ->getJson('/api/v1/platform/schools')
        ->assertOk()
        ->json('data');

    expect(collect($listed)->pluck('id'))->toContain($fixture['school']->id)
        ->and(collect($listed)->firstWhere('id', $fixture['school']->id))->not->toHaveKey('remaining_amount')
        ->and(collect($listed)->firstWhere('id', $fixture['school']->id))->not->toHaveKey('invoices');

    $this->actingAs($fixture['account'], 'sanctum')
        ->getJson('/api/v1/platform/schools')
        ->assertNotFound();

    $this->actingAs($fixture['account'], 'sanctum')
        ->postJson('/api/v1/platform/schools', ['name' => 'Intrusion'])
        ->assertNotFound();
});

it('does not attach the platform admin to the school they create', function () {
    $platform = $this->provisionPlatformAdmin();

    $created = $this->actingAs($platform, 'sanctum')
        ->postJson('/api/v1/platform/schools', [
            'name' => 'École Ivato',
            'code' => 'ivato-test',
        ])
        ->assertCreated()
        ->json('data');

    expect(TenantContext::runWithRlsBypass(fn () => SchoolRoleAssignment::query()
        ->withoutGlobalScopes()
        ->where('school_id', $created['id'])
        ->where('person_id', $platform->person_id)
        ->exists()))->toBeFalse()
        ->and($created['admins'])->toBe([]);
});
