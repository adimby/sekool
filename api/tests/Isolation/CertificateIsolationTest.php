<?php

use App\Domain\Certificate\Models\Certificate;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B certificates', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);
    $core = $this->provisionFeeSchedule($a);

    $classroom = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/classrooms", [
            'school_year_id' => $a['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $certificateId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/enrollments/{$family['enrollment']->id}/certificates")
        ->assertCreated()
        ->json('data.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(Certificate::query()->pluck('id'))->not->toContain($certificateId);
    $ids = collect(DB::select('select id from certificates'))->pluck('id');
    expect($ids)->not->toContain($certificateId);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/certificates")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($b['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/certificates/{$certificateId}/revoke", [
            'reason' => 'Intrusion',
        ])
        ->assertNotFound();
});
