<?php

use App\Domain\Academic\Models\GradeEntry;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B grade entries', function () {
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

    $subjectId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/subjects", ['name' => 'Malagasy'])
        ->json('data.id');

    $entryId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/grades", [
            'enrollment_id' => $family['enrollment']->id,
            'subject_id' => $subjectId,
            'value' => 11,
            'assessed_on' => '2026-10-02',
        ])
        ->assertCreated()
        ->json('data.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(GradeEntry::query()->pluck('id'))->not->toContain($entryId);
    $ids = collect(DB::select('select id from grade_entries'))->pluck('id');
    expect($ids)->not->toContain($entryId);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/grades")
        ->assertNotFound();
});
