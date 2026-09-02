<?php

use App\Domain\Communication\Models\Message;
use App\Domain\Platform\Tenancy\TenantContext;
use Carbon\Carbon;

it('blocks teacher and room clashes across classes and records a substitution and an exam', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classA = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $classB = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème B',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classA['id'],
        ]);

    $teacher = $this->provisionTeacher($school, $classA['id']);
    $substitute = $this->provisionTeacher($school, $classB['id']);

    $slot = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classA['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '07:30',
            'ends_at' => '08:25',
            'subject' => 'Malagasy',
            'teacher_person_id' => $teacher['account']->person_id,
            'room' => 'A1',
        ])
        ->assertCreated()
        ->json('data.timetable.0');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classB['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '07:30',
            'ends_at' => '08:25',
            'subject' => 'Français',
            'teacher_person_id' => $teacher['account']->person_id,
            'room' => 'B2',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Ce professeur a déjà un cours à cette heure.');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classB['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '07:30',
            'ends_at' => '08:25',
            'subject' => 'Français',
            'teacher_person_id' => $substitute['account']->person_id,
            'room' => 'A1',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Cette salle est déjà prise à cette heure.');

    $overview = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/timetable")
        ->assertOk()
        ->json();

    expect($overview['data'])->not->toBeEmpty()
        ->and(collect($overview['data'])->pluck('classroom'))->toContain('6ème A');

    $monday = Carbon::now();
    if ((int) $monday->isoWeekday() !== 1) {
        $monday = $monday->next(Carbon::MONDAY);
    }

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/timetable/{$slot['id']}/substitutions", [
            'on_date' => $monday->toDateString(),
            'substitute_person_id' => $substitute['account']->person_id,
        ])
        ->assertForbidden();

    $sub = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/timetable/{$slot['id']}/substitutions", [
            'on_date' => $monday->toDateString(),
            'substitute_person_id' => $substitute['account']->person_id,
            'reason' => 'Indisponibilité du titulaire.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.cancelled', false)
        ->json('data');

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classA['id']}/exams", [
            'title' => 'Composition Malagasy',
            'held_on' => now()->addDays(3)->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'room' => 'A1',
        ])
        ->assertForbidden();

    $exam = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classA['id']}/exams", [
            'title' => 'Composition Malagasy',
            'subject' => 'Malagasy',
            'held_on' => now()->addDays(3)->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'room' => 'A1',
            'photo' => 'http://example.test/interdit.jpg',
        ])
        ->assertCreated()
        ->assertJsonMissingPath('data.photo')
        ->assertJsonPath('data.title', 'Composition Malagasy')
        ->json('data');

    expect($exam)->not->toHaveKey('photo');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classA['id']}/exams", [
            'title' => 'Composition Français',
            'held_on' => now()->addDays(3)->toDateString(),
            'starts_at' => '08:30',
            'ends_at' => '09:30',
            'room' => 'C3',
        ])
        ->assertStatus(422);

    $parentTimetable = $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/timetable")
        ->assertOk()
        ->json();

    expect($parentTimetable['data'])->not->toBeEmpty()
        ->and($parentTimetable['substitutions'])->not->toBeEmpty()
        ->and($parentTimetable['substitutions'][0]['id'])->toBe($sub['id']);

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/exams")
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Composition Malagasy');

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    expect(Message::query()->where('template_key', 'exam_session')->count())->toBeGreaterThan(0)
        ->and(Message::query()->where('template_key', 'timetable_substitution')->count())->toBeGreaterThan(0);
    TenantContext::clear();
});
