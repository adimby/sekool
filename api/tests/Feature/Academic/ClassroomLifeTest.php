<?php

it('lets direction run a class file: titulaire, enseignants, délégués, EDT, conseil et activité', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
            'capacity' => 40,
        ])
        ->assertCreated()
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $teacher = $this->provisionTeacher($school);

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}", [
            'main_teacher_person_id' => $teacher['account']->person_id,
            'delegate_person_id' => $family['student']->id,
            'capacity' => 42,
        ])
        ->assertOk()
        ->assertJsonPath('data.main_teacher.id', $teacher['account']->person_id)
        ->assertJsonPath('data.delegate.id', $family['student']->id)
        ->assertJsonPath('data.capacity', 42);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/teachers", [
            'person_id' => $school['account']->person_id,
            'subject' => 'Histoire',
        ])
        ->assertCreated();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '07:30',
            'ends_at' => '08:25',
            'subject' => 'Malagasy',
            'teacher_person_id' => $teacher['account']->person_id,
            'room' => 'A1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.timetable.0.subject', 'Malagasy');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/timetable", [
            'weekday' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'subject' => 'Chevauchement',
        ])
        ->assertStatus(422);

    $year = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/years/{$school['year']->id}")
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/councils", [
            'held_on' => '2026-12-10',
            'title' => 'Conseil T1',
            'minutes' => 'Classe attentive.',
            'status' => 'held',
            'academic_term_id' => $year['terms'][0]['id'] ?? null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.councils.0.title', 'Conseil T1');

    $file = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/activities", [
            'type' => 'outing',
            'title' => 'Sortie Jardin botanique',
            'held_on' => '2026-10-02',
            'location' => 'Tsimbazaza',
        ])
        ->assertCreated()
        ->json('data');

    expect($file['students'])->toHaveCount(1)
        ->and($file['students'][0]['office'])->toBe('delegate')
        ->and($file['teachers'])->toHaveCount(2)
        ->and($file['activities'][0]['type'])->toBe('outing');

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}")
        ->assertOk()
        ->assertJsonPath('data.classroom.name', '6ème A');

    $this->actingAs($teacher['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}", [
            'capacity' => 10,
        ])
        ->assertForbidden();
});

it('exposes the grade stage on the class file', function () {
    $school = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])
        ->assertCreated()
        ->assertJsonPath('data.grade_level.stage', 'middle')
        ->assertJsonPath('data.grade_level.stage_label', 'Collège');
});

it('returns a preschool class file without a delegate', function () {
    $school = $this->provisionSchool();
    $schoolId = $school['school']->id;

    $grade = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/grade-levels", [
            'name' => 'GS',
            'stage' => 'preschool',
            'sequence' => 0,
        ])
        ->assertCreated()
        ->json('data');

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $grade['id'],
            'name' => 'GS A',
            'capacity' => 25,
        ])
        ->assertCreated()
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}")
        ->assertOk()
        ->assertJsonPath('data.classroom.grade_level.stage', 'preschool')
        ->assertJsonPath('data.classroom.grade_level.stage_label', 'Maternelle')
        ->assertJsonPath('data.classroom.delegate', null)
        ->assertJsonPath('data.headcount', 0);
});

it('refuses a delegate who is not in the class', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}", [
            'delegate_person_id' => $family['student']->id,
        ])
        ->assertStatus(422);
});

it('clears the delegate when the student leaves the class', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $first = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])
        ->json('data');

    $second = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème B',
        ])
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $first['id'],
        ]);

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/classrooms/{$first['id']}", [
            'delegate_person_id' => $family['student']->id,
        ])
        ->assertOk();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $second['id'],
        ]);

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$first['id']}")
        ->assertOk()
        ->assertJsonPath('data.classroom.delegate', null);
});
