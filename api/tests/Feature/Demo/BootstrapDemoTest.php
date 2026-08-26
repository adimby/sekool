<?php

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
        ->assertJsonStructure(['token', 'person_id', 'person', 'schools'])
        ->assertJsonPath('schools.0.code', 'antsahabe');
});
