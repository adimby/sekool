<?php

use App\Domain\Collection\Actions\RecomputeCollection;
use App\Domain\Collection\Models\RiskAssessment;
use App\Domain\Finance\Models\Installment;
use App\Domain\Platform\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'Indian/Antananarivo'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('never lets school A read school B risk assessments through Eloquent or RLS', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($a);
    $this->provisionFeeSchedule($a);

    $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/enrollments/{$familyA['enrollment']->id}/invoices")
        ->assertCreated();

    TenantContext::activate(TenantContext::forSchool($a['school']->id, $a['account']->person_id));
    $installment = Installment::query()
        ->whereHas('invoice', fn ($query) => $query->where('enrollment_id', $familyA['enrollment']->id))
        ->orderBy('sequence')
        ->firstOrFail();
    $installment->forceFill(['due_on' => '2026-06-01'])->save();
    app(RecomputeCollection::class)->execute($a['school']->id, live: true);
    $riskId = RiskAssessment::query()->where('enrollment_id', $familyA['enrollment']->id)->value('id');
    TenantContext::clear();

    expect($riskId)->not->toBeNull();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));

    expect(RiskAssessment::query()->pluck('id'))->not->toContain($riskId);

    $ids = collect(DB::select('select id from risk_assessments'))->pluck('id');
    expect($ids)->not->toContain($riskId);

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/enrollments/{$familyA['enrollment']->id}/risk")
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/enrollments/{$familyA['enrollment']->id}/risk")
        ->assertNotFound();
});
