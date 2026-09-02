<?php

use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school B replay school A attendance as offline_sync or complete A totp', function () {
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

    $teacherA = $this->provisionTeacher($a, $classroom['id']);
    $teacherB = $this->provisionTeacher($b);

    $reference = 'dddddddd-4444-4444-8444-444444444444';

    $this->actingAs($teacherB['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/attendance", [
            'date' => now()->toDateString(),
            'session' => AttendanceSession::FullDay->value,
            'recorded_via' => 'offline_sync',
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Present->value,
                'client_reference' => $reference,
            ]],
        ])
        ->assertNotFound();

    $created = $this->actingAs($teacherA['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/attendance", [
            'date' => now()->toDateString(),
            'session' => AttendanceSession::FullDay->value,
            'recorded_via' => 'offline_sync',
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Present->value,
                'client_reference' => $reference,
            ]],
        ])
        ->assertCreated()
        ->json('data.0.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(AttendanceRecord::query()->pluck('id'))->not->toContain($created)
        ->and(collect(DB::select('select id from attendance_records'))->pluck('id'))->not->toContain($created);
    TenantContext::clear();

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => $a['account']->email,
        'password' => 'password',
    ])->json();

    $this->actingAs($b['account'], 'sanctum')
        ->postJson('/api/v1/auth/totp', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => '000000',
        ])
        ->assertUnprocessable();
});
