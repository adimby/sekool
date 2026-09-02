<?php

use App\Domain\Academic\Enums\ClassPostKind;
use App\Domain\Academic\Enums\DisciplinaryMeasureType;
use App\Domain\Academic\Enums\SchoolEventAudience;
use App\Domain\Academic\Enums\SchoolEventType;
use App\Domain\Academic\Models\ClassPost;
use App\Domain\Academic\Models\DisciplinaryCase;
use App\Domain\Academic\Models\SchoolEvent;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B class posts, discipline or events', function () {
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

    $postId = $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/posts", [
            'kind' => ClassPostKind::Homework->value,
            'title' => 'Devoir isolé',
            'body' => 'Ne pas laisser voir l’autre école.',
            'due_on' => '2026-09-12',
        ])
        ->assertCreated()
        ->json('data.id');

    $caseId = $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/discipline", [
            'enrollment_id' => $family['enrollment']->id,
            'occurred_on' => '2026-09-04',
            'fact' => 'Constat interne.',
            'measure_type' => DisciplinaryMeasureType::Warning->value,
        ])
        ->assertCreated()
        ->json('data.id');

    $eventId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/events", [
            'type' => SchoolEventType::Meeting->value,
            'title' => 'Réunion interne',
            'starts_on' => '2026-09-15',
            'audience' => SchoolEventAudience::School->value,
        ])
        ->assertCreated()
        ->json('data.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(ClassPost::query()->pluck('id'))->not->toContain($postId)
        ->and(DisciplinaryCase::query()->pluck('id'))->not->toContain($caseId)
        ->and(SchoolEvent::query()->pluck('id'))->not->toContain($eventId)
        ->and(collect(DB::select('select id from class_posts'))->pluck('id'))->not->toContain($postId)
        ->and(collect(DB::select('select id from disciplinary_cases'))->pluck('id'))->not->toContain($caseId)
        ->and(collect(DB::select('select id from school_events'))->pluck('id'))->not->toContain($eventId);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/posts")
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/discipline")
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/events")
        ->assertNotFound();
});
