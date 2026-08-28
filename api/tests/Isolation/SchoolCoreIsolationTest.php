<?php

use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B invoices through Eloquent or RLS', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($a);
    $this->provisionFeeSchedule($a);
    $this->provisionFeeSchedule($b);

    $invoiceId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/enrollments/{$familyA['enrollment']->id}/invoices")
        ->assertCreated()
        ->json('data.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));

    expect(Invoice::query()->pluck('id'))->not->toContain($invoiceId);

    $ids = collect(DB::select('select id from invoices'))->pluck('id');
    expect($ids)->not->toContain($invoiceId);

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/enrollments/{$familyA['enrollment']->id}/invoice")
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/enrollments/{$familyA['enrollment']->id}/invoice")
        ->assertNotFound();
});

it('never lets school A read school B attendance through Eloquent or RLS', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($a);
    $coreA = $this->provisionFeeSchedule($a);

    $classroom = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/classrooms", [
            'school_year_id' => $a['year']->id,
            'grade_level_id' => $coreA['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/enrollments/{$familyA['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $teacher = $this->provisionTeacher($a, $classroom['id']);

    $recordId = $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/attendance", [
            'date' => '2026-09-15',
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $familyA['enrollment']->id,
                'status' => 'present',
            ]],
        ])->json('data.0.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));

    expect(AttendanceRecord::query()->pluck('id'))->not->toContain($recordId);

    $ids = collect(DB::select('select id from attendance_records'))->pluck('id');
    expect($ids)->not->toContain($recordId);
});

it('rejects assigning a classroom that belongs to another school', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($a);
    $coreB = $this->provisionFeeSchedule($b);

    $classroomB = $this->actingAs($b['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$b['school']->id}/classrooms", [
            'school_year_id' => $b['year']->id,
            'grade_level_id' => $coreB['grade']->id,
            'name' => '6ème B',
        ])->json('data');

    $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/enrollments/{$familyA['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroomB['id'],
        ])
        ->assertNotFound();
});

it('hides another family\'s finance from a parent', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($a);
    $familyB = $this->provisionEnrolledFamily($b);
    $this->provisionFeeSchedule($a);

    $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/enrollments/{$familyA['enrollment']->id}/invoices")
        ->assertCreated();

    $this->actingAs($familyB['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$familyA['student']->id}/finance")
        ->assertNotFound();
});
