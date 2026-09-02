<?php

use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school B write period attendance on a slot of school A', function () {
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

    $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '07:30',
            'ends_at' => '08:25',
            'subject' => 'Malagasy',
            'teacher_person_id' => $teacherA['account']->person_id,
            'room' => 'A1',
        ])
        ->assertCreated();

    $slotId = collect(
        $this->actingAs($a['account'], 'sanctum')
            ->getJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}")
            ->json('data.timetable'),
    )->firstWhere('subject', 'Malagasy')['id'];

    $this->actingAs($teacherB['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/attendance", [
            'date' => '2026-09-14',
            'timetable_slot_id' => $slotId,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])
        ->assertNotFound();

    $this->actingAs($teacherB['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$b['school']->id}/attendance", [
            'date' => '2026-09-14',
            'timetable_slot_id' => $slotId,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])
        ->assertNotFound();

    $created = $this->actingAs($teacherA['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/attendance", [
            'date' => '2026-09-14',
            'timetable_slot_id' => $slotId,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])
        ->assertCreated()
        ->json('data.0.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(AttendanceRecord::query()->pluck('id'))->not->toContain($created)
        ->and(collect(DB::select('select id from attendance_records'))->pluck('id'))->not->toContain($created);
    TenantContext::clear();
});
