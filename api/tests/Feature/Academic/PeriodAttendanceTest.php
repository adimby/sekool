<?php

use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Communication\Models\Message;
use App\Domain\Platform\Tenancy\TenantContext;
use Carbon\Carbon;

it('lets a subject teacher record attendance on their slot, not the titulaire’s other course', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $titulaire = $this->provisionTeacher($school, $classroom['id']);
    $maths = $this->provisionTeacher($school);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/teachers", [
            'person_id' => $maths['account']->person_id,
            'subject' => 'Mathématiques',
        ])
        ->assertCreated();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '07:30',
            'ends_at' => '08:25',
            'subject' => 'Malagasy',
            'teacher_person_id' => $titulaire['account']->person_id,
            'room' => 'A1',
        ])
        ->assertCreated();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '08:30',
            'ends_at' => '09:25',
            'subject' => 'Mathématiques',
            'teacher_person_id' => $maths['account']->person_id,
            'room' => 'A1',
        ])
        ->assertCreated();

    $slots = collect(
        $this->actingAs($school['account'], 'sanctum')
            ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}")
            ->json('data.timetable'),
    );
    $malagasyId = $slots->firstWhere('subject', 'Malagasy')['id'];
    $mathsId = $slots->firstWhere('subject', 'Mathématiques')['id'];

    $monday = Carbon::parse('2026-09-14');
    expect($monday->isoWeekday())->toBe(1);

    $this->actingAs($titulaire['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $monday->toDateString(),
            'session' => AttendanceSession::FullDay->value,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Present->value,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Choisissez le cours.');

    $this->actingAs($maths['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $monday->toDateString(),
            'session' => AttendanceSession::Period->value,
            'timetable_slot_id' => $malagasyId,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Absent->value,
                'reason' => 'Maladie',
            ]],
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'L’appel se fait par le professeur du cours.');

    $this->actingAs($maths['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $monday->toDateString(),
            'session' => AttendanceSession::Period->value,
            'timetable_slot_id' => $mathsId,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Absent->value,
                'reason' => 'Maladie',
                'justification' => 'Vu à l’accueil',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.status', 'absent')
        ->assertJsonPath('data.0.timetable_slot_id', $mathsId);

    $this->actingAs($titulaire['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $monday->toDateString(),
            'session' => AttendanceSession::Period->value,
            'timetable_slot_id' => $malagasyId,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Present->value,
            ]],
        ])
        ->assertCreated();

    TenantContext::runWithRlsBypass(function () use ($family): void {
        expect(AttendanceRecord::query()->withoutGlobalScopes()->where('enrollment_id', $family['enrollment']->id)->count())->toBe(2)
            ->and(Message::query()->withoutGlobalScopes()->where('template_key', 'same_day_absence')->where('channel', 'in_app')->count())->toBe(1);
    });

    $index = $this->actingAs($maths['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/attendance?".http_build_query([
            'classroom_id' => $classroom['id'],
            'date' => $monday->toDateString(),
            'timetable_slot_id' => $mathsId,
        ]))
        ->assertOk()
        ->assertJsonPath('requires_course', true)
        ->assertJsonPath('data.0.attendance.status', 'absent')
        ->json();

    expect($index['data'][0])->toHaveKey('student_number')
        ->and(collect($index['courses'])->pluck('subject')->all())->toContain('Malagasy', 'Mathématiques');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/attendance?".http_build_query([
            'from' => $monday->toDateString(),
            'to' => $monday->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonFragment(['subject' => 'Mathématiques']);
});

it('lets a substitute take the roll and forbids attendance on a cancelled course', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $titulaire = $this->provisionTeacher($school, $classroom['id']);
    $substitute = $this->provisionTeacher($school);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '07:30',
            'ends_at' => '08:25',
            'subject' => 'Malagasy',
            'teacher_person_id' => $titulaire['account']->person_id,
            'room' => 'A1',
        ])
        ->assertCreated();

    $slotId = collect(
        $this->actingAs($school['account'], 'sanctum')
            ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}")
            ->json('data.timetable'),
    )->firstWhere('subject', 'Malagasy')['id'];

    $monday = '2026-09-14';
    $tuesday = '2026-09-15';

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/timetable/{$slotId}/substitutions", [
            'on_date' => $monday,
            'substitute_person_id' => $substitute['account']->person_id,
            'reason' => 'Indisponibilité du titulaire.',
        ])
        ->assertCreated();

    $this->actingAs($titulaire['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $monday,
            'timetable_slot_id' => $slotId,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])
        ->assertForbidden();

    $this->actingAs($substitute['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms")
        ->assertOk()
        ->assertJsonPath('data.0.id', $classroom['id']);

    $this->actingAs($substitute['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $monday,
            'timetable_slot_id' => $slotId,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])
        ->assertCreated();

    $this->actingAs($titulaire['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $tuesday,
            'timetable_slot_id' => $slotId,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Ce cours n’a pas lieu à cette date.');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/timetable", [
            'weekday' => 2,
            'starts_at' => '07:30',
            'ends_at' => '08:25',
            'subject' => 'Malagasy',
            'teacher_person_id' => $titulaire['account']->person_id,
            'room' => 'A1',
        ])
        ->assertCreated();

    $tuesdaySlot = collect(
        $this->actingAs($school['account'], 'sanctum')
            ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}")
            ->json('data.timetable'),
    )->first(fn (array $row): bool => $row['subject'] === 'Malagasy' && (int) $row['weekday'] === 2)['id'];

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/timetable/{$tuesdaySlot}/substitutions", [
            'on_date' => $tuesday,
            'reason' => 'Cours annulé.',
        ])
        ->assertCreated();

    $this->actingAs($titulaire['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $tuesday,
            'timetable_slot_id' => $tuesdaySlot,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Ce cours est annulé.');
});
