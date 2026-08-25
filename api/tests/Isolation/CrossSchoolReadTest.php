<?php

use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Support\Facades\DB;

it('connects as a role that cannot bypass row level security', function () {
    $role = DB::selectOne(
        'select rolsuper, rolbypassrls from pg_roles where rolname = current_user'
    );

    expect($role)->not->toBeNull()
        ->and(filter_var($role->rolsuper, FILTER_VALIDATE_BOOLEAN))->toBeFalse()
        ->and(filter_var($role->rolbypassrls, FILTER_VALIDATE_BOOLEAN))->toBeFalse();
});

it('never lets school A read school B years through Eloquent', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();

    TenantContext::activate(TenantContext::forSchool($a['school']->id, $a['account']->person_id));

    $visible = SchoolYear::query()->pluck('id');

    expect($visible)->toContain($a['year']->id)
        ->and($visible)->not->toContain($b['year']->id);
});

it('returns a uniform 404 when school A requests school B year via API', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/years/{$b['year']->id}")
        ->assertNotFound();

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/years/{$b['year']->id}")
        ->assertNotFound();
});

it('lists only the current school years via API', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/years")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $a['year']->id);
});

it('blocks raw SQL without tenant context via RLS', function () {
    $this->provisionSchool();

    TenantContext::clear();

    $rows = DB::select('select id from school_years');

    expect($rows)->toBeEmpty();
});

it('allows raw SQL only for the current school via RLS', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();

    TenantContext::activate(TenantContext::forSchool($a['school']->id, $a['account']->person_id));

    $ids = collect(DB::select('select id from school_years'))->pluck('id');

    expect($ids)->toContain($a['year']->id)
        ->and($ids)->not->toContain($b['year']->id);
});
