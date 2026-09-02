<?php

use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Communication\Models\Message;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B print letters or a parent export another family', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);
    $other = $this->provisionEnrolledFamily($b);
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

    $teacher = $this->provisionTeacher($a, $classroom['id']);

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/attendance", [
            'date' => now()->toDateString(),
            'session' => AttendanceSession::FullDay->value,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Absent->value,
                'reason' => 'Maladie',
                'client_reference' => 'cccccccc-3333-4333-8333-333333333333',
            ]],
        ])
        ->assertCreated();

    TenantContext::activate(TenantContext::forSchool($a['school']->id, $a['account']->person_id));
    $letterId = Message::query()->where('channel', 'print')->value('id');
    TenantContext::clear();

    expect($letterId)->not->toBeNull();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(Message::query()->pluck('id'))->not->toContain($letterId)
        ->and(collect(DB::select('select id from messages'))->pluck('id'))->not->toContain($letterId);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/messages/outbox")
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/messages/{$letterId}/printed")
        ->assertNotFound();

    $archive = $this->actingAs($other['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/export')
        ->assertOk()
        ->json();

    expect(collect($archive['children'])->pluck('person.id'))->not->toContain($family['student']->id);
});
