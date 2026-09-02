<?php

use App\Domain\Academic\Models\ExamSession;
use App\Domain\Academic\Models\TimetableSubstitution;
use App\Domain\Platform\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B timetable substitutions or exams', function () {
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

    $teacher = $this->provisionTeacher($a, $classroom['id']);

    $slot = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '07:30',
            'ends_at' => '08:25',
            'subject' => 'Malagasy',
            'teacher_person_id' => $teacher['account']->person_id,
            'room' => 'A1',
        ])
        ->assertCreated()
        ->json('data.timetable.0');

    $monday = Carbon::now();
    if ((int) $monday->isoWeekday() !== 1) {
        $monday = $monday->next(Carbon::MONDAY);
    }

    $subId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/timetable/{$slot['id']}/substitutions", [
            'on_date' => $monday->toDateString(),
            'reason' => 'Cours reporté.',
        ])
        ->assertCreated()
        ->json('data.id');

    $examId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/exams", [
            'title' => 'Composition isolée',
            'held_on' => now()->addDays(4)->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '10:00',
        ])
        ->assertCreated()
        ->json('data.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(TimetableSubstitution::query()->pluck('id'))->not->toContain($subId)
        ->and(ExamSession::query()->pluck('id'))->not->toContain($examId)
        ->and(collect(DB::select('select id from timetable_substitutions'))->pluck('id'))->not->toContain($subId)
        ->and(collect(DB::select('select id from exam_sessions'))->pluck('id'))->not->toContain($examId);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/timetable")
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/exams")
        ->assertNotFound();
});
