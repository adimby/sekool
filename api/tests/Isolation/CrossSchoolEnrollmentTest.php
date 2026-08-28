<?php

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B enrollments through Eloquent', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($a);
    $familyB = $this->provisionEnrolledFamily($b);

    TenantContext::activate(TenantContext::forSchool($a['school']->id, $a['account']->person_id));

    $visible = Enrollment::query()->pluck('id');

    expect($visible)->toContain($familyA['enrollment']->id)
        ->and($visible)->not->toContain($familyB['enrollment']->id);
});

it('blocks raw SQL enrollment reads across schools via RLS', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($a);
    $this->provisionEnrolledFamily($b);

    TenantContext::activate(TenantContext::forSchool($a['school']->id, $a['account']->person_id));

    $ids = collect(DB::select('select id from enrollments'))->pluck('id');

    expect($ids)->toContain($familyA['enrollment']->id)
        ->and($ids)->toHaveCount(1);
});

it('returns a uniform 404 when school A requests a person linked only to school B', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $familyB = $this->provisionEnrolledFamily($b);

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/people/{$familyB['student']->id}")
        ->assertNotFound();

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/people/{$familyB['student']->id}")
        ->assertNotFound();
});
