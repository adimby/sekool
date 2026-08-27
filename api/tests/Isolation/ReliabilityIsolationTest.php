<?php

use App\Domain\Collection\Actions\RecomputeCollection;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\Reliability\Models\ReliabilityScore;
use App\Domain\Reliability\Support\ReliabilityIndexes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'Indian/Antananarivo'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('never lets school A read school B reliability scores through Eloquent, RLS or HTTP', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($a);
    $this->provisionFeeSchedule($a);

    $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/enrollments/{$familyA['enrollment']->id}/invoices")
        ->assertCreated();

    TenantContext::activate(TenantContext::forSchool($a['school']->id, $a['account']->person_id));
    app(RecomputeCollection::class)->execute($a['school']->id, live: false);
    $scoreId = ReliabilityScore::query()
        ->where('index_type', ReliabilityIndexes::SCHOOL)
        ->where('subject_id', $a['school']->id)
        ->value('id');
    TenantContext::clear();

    expect($scoreId)->not->toBeNull();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));

    expect(ReliabilityScore::query()->pluck('id'))->not->toContain($scoreId);

    $ids = collect(DB::select('select id from reliability_scores'))->pluck('id');
    expect($ids)->not->toContain($scoreId);

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/reliability/school")
        ->assertNotFound();

    $foreign = $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/reliability/school")
        ->assertOk()
        ->json('data');

    expect($foreign['id'])->not->toBe($scoreId)
        ->and($foreign['subject_id'])->toBe($b['school']->id);

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/families/{$familyA['family']->id}/relationship")
        ->assertNotFound();

    TenantContext::clear();
});
