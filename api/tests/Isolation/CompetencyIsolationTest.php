<?php

use App\Domain\Academic\Models\BulletinComment;
use App\Domain\Academic\Models\CompetencyAssessment;
use App\Domain\Academic\Models\CompetencyDomain;
use App\Domain\Academic\Models\CompetencyItem;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B livret or bulletin comments', function () {
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

    $commentId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/enrollments/{$family['enrollment']->id}/bulletin/comments", [
            'body' => 'Travail régulier ce trimestre.',
        ])
        ->assertCreated()
        ->json('data.id');

    TenantContext::activate(TenantContext::forSchool($a['school']->id, $a['account']->person_id));
    $domain = CompetencyDomain::query()->create([
        'school_id' => $a['school']->id,
        'stage' => 'primary',
        'code' => 'MATHS',
        'label' => 'Mathématiques',
        'sequence' => 1,
    ]);
    $item = CompetencyItem::query()->create([
        'school_id' => $a['school']->id,
        'domain_id' => $domain->id,
        'label' => 'Nombres et calcul',
        'sequence' => 1,
    ]);
    $assessment = CompetencyAssessment::query()->create([
        'school_id' => $a['school']->id,
        'enrollment_id' => $family['enrollment']->id,
        'classroom_id' => $classroom['id'],
        'competency_item_id' => $item->id,
        'level' => 'acquired',
        'assessed_on' => '2026-09-04',
        'recorded_by_person_id' => $a['account']->person_id,
    ]);
    TenantContext::clear();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(BulletinComment::query()->pluck('id'))->not->toContain($commentId)
        ->and(CompetencyDomain::query()->pluck('id'))->not->toContain($domain->id)
        ->and(CompetencyItem::query()->pluck('id'))->not->toContain($item->id)
        ->and(CompetencyAssessment::query()->pluck('id'))->not->toContain($assessment->id)
        ->and(collect(DB::select('select id from bulletin_comments'))->pluck('id'))->not->toContain($commentId)
        ->and(collect(DB::select('select id from competency_domains'))->pluck('id'))->not->toContain($domain->id)
        ->and(collect(DB::select('select id from competency_items'))->pluck('id'))->not->toContain($item->id)
        ->and(collect(DB::select('select id from competency_assessments'))->pluck('id'))->not->toContain($assessment->id);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom['id']}/competencies")
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/enrollments/{$family['enrollment']->id}/bulletin")
        ->assertNotFound();
});
