<?php

use App\Domain\Communication\Models\Message;
use App\Domain\Platform\Tenancy\TenantContext;

it('lets a teacher publish homework and a detention that the parent can see', function () {
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

    $teacher = $this->provisionTeacher($school, $classroom['id']);
    $otherClass = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème B',
        ])->json('data');
    $outsider = $this->provisionTeacher($school, $otherClass['id']);

    $homework = $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/posts", [
            'kind' => 'homework',
            'title' => 'Exercices Malagasy',
            'body' => 'Faire les exercices 1 à 4 page 12.',
            'due_on' => '2026-09-10',
            'attachment_name' => 'consigne.txt',
            'attachment_body' => 'Relire le poème avant de copier.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'homework')
        ->assertJsonPath('data.title', 'Exercices Malagasy')
        ->assertJsonPath('data.attachment_name', 'consigne.txt')
        ->json('data');

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/posts", [
            'kind' => 'journal',
            'title' => 'Journée calme',
            'body' => 'La classe a travaillé le poème.',
            'held_on' => '2026-09-04',
        ])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'journal');

    $detention = $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/discipline", [
            'enrollment_id' => $family['enrollment']->id,
            'occurred_on' => '2026-09-04',
            'fact' => 'Bavardage répété pendant le cours.',
            'measure_type' => 'detention',
            'measure_label' => 'Retenue',
            'measure_on' => '2026-09-08',
        ])
        ->assertCreated()
        ->assertJsonPath('data.measure_type', 'detention')
        ->assertJsonPath('data.measure_label', 'Retenue')
        ->json('data');

    $this->actingAs($outsider['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/posts", [
            'kind' => 'homework',
            'title' => 'Devoir d’une autre classe',
            'body' => 'Ne doit pas passer.',
            'due_on' => '2026-09-11',
        ])
        ->assertNotFound();

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/events", [
            'type' => 'open_day',
            'title' => 'Portes ouvertes',
            'starts_on' => '2026-09-20',
            'audience' => 'classroom',
            'classroom_id' => $classroom['id'],
        ])
        ->assertForbidden();

    $parentPosts = $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/posts")
        ->assertOk()
        ->json('data');

    expect(collect($parentPosts)->pluck('title'))->toContain('Exercices Malagasy')
        ->and(collect($parentPosts)->pluck('kind'))->toContain('journal')
        ->and(collect($parentPosts)->firstWhere('id', $homework['id'])['attachment_body'])->toBe('Relire le poème avant de copier.');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/discipline")
        ->assertOk()
        ->assertJsonPath('data.0.id', $detention['id'])
        ->assertJsonPath('data.0.measure_label', 'Retenue');

    $inbox = $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/messages')
        ->assertOk()
        ->json('data');

    $keys = collect($inbox)->pluck('template_key');
    expect($keys)->toContain('homework_published')
        ->and($keys)->toContain('journal_published')
        ->and($keys)->toContain('discipline_measure')
        ->and(mb_strtolower(json_encode($inbox)))->not->toContain('score')
        ->and(mb_strtolower(json_encode($inbox)))->not->toContain('risque')
        ->and(mb_strtolower(json_encode($inbox)))->not->toContain('élève en difficulté');

    TenantContext::runWithRlsBypass(function () use ($homework, $family): void {
        expect(Message::query()->withoutGlobalScopes()->where('template_key', 'homework_published')->where('channel', 'in_app')->where('subject_person_id', $family['student']->id)->count())->toBe(1)
            ->and(Message::query()->withoutGlobalScopes()->where('template_key', 'homework_published')->where('channel', 'print')->count())->toBe(1)
            ->and(Message::query()->withoutGlobalScopes()->where('template_key', 'journal_published')->where('channel', 'print')->count())->toBe(0)
            ->and(Message::query()->withoutGlobalScopes()->where('idempotency_key', 'like', 'homework_published:in_app:'.$homework['id'].'%')->count())->toBe(1);
    });

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/posts", [
            'kind' => 'homework',
            'title' => 'Exercices Malagasy',
            'body' => 'Faire les exercices 1 à 4 page 12.',
            'due_on' => '2026-09-10',
        ])
        ->assertCreated();

    TenantContext::runWithRlsBypass(function () use ($family): void {
        expect(Message::query()->withoutGlobalScopes()->where('template_key', 'homework_published')->where('channel', 'in_app')->where('subject_person_id', $family['student']->id)->count())->toBe(2);
    });
});

it('lets direction publish a class event that only that class family sees', function () {
    $school = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($school, student: ['first_name' => 'Hery', 'last_name' => 'Rasoanaivo']);
    $familyB = $this->provisionEnrolledFamily($school, student: ['first_name' => 'Fanja', 'last_name' => 'Rakoto']);
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
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$familyA['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classA['id'],
        ]);
    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$familyB['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classB['id'],
        ]);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/events", [
            'type' => 'open_day',
            'title' => 'Portes ouvertes 6ème A',
            'body' => 'Accueil des familles dans la salle A1.',
            'starts_on' => '2026-09-20',
            'audience' => 'classroom',
            'classroom_id' => $classA['id'],
            'location' => 'Salle A1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.audience', 'classroom')
        ->assertJsonPath('data.classroom_id', $classA['id']);

    $eventsA = $this->actingAs($familyA['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$familyA['student']->id}/events")
        ->assertOk()
        ->json('data');

    $eventsB = $this->actingAs($familyB['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$familyB['student']->id}/events")
        ->assertOk()
        ->json('data');

    expect(collect($eventsA)->pluck('title'))->toContain('Portes ouvertes 6ème A')
        ->and(collect($eventsB)->pluck('title'))->not->toContain('Portes ouvertes 6ème A');

    $inboxA = $this->actingAs($familyA['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/messages')
        ->json('data');
    $inboxB = $this->actingAs($familyB['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/messages')
        ->json('data');

    expect(collect($inboxA)->pluck('template_key'))->toContain('school_event')
        ->and(collect($inboxB)->pluck('template_key'))->not->toContain('school_event');

    $teacherA = $this->provisionTeacher($school, $classA['id']);
    $this->actingAs($teacherA['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/events?classroom_id={$classA['id']}")
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Portes ouvertes 6ème A');
});
