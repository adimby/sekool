<?php

use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B fee schedules through Eloquent or RLS', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();

    $scheduleId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/fee-schedules", [
            'school_year_id' => $a['year']->id,
            'name' => 'Écolage A',
            'items' => [[
                'code' => 'SCOL_T1',
                'label' => 'Écolage 1er trimestre',
                'amount' => 50_000,
                'due_on' => '2026-09-15',
                'category' => FeeCategory::Tuition->value,
            ]],
        ])
        ->assertCreated()
        ->json('data.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));

    expect(FeeSchedule::query()->pluck('id'))->not->toContain($scheduleId);

    $ids = collect(DB::select('select id from fee_schedules'))->pluck('id');
    expect($ids)->not->toContain($scheduleId);

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/fee-schedules")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/fee-schedules/{$scheduleId}")
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$a['school']->id}/fee-schedules/{$scheduleId}", [
            'name' => 'Intrusion',
        ])
        ->assertNotFound();
});

it('never lets school A read school B expenses', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();

    $expenseId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/expenses", [
            'school_year_id' => $a['year']->id,
            'kind' => 'expense',
            'label' => 'Eau',
            'amount' => 12_000,
            'spent_on' => '2026-09-01',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/expenses")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/expenses")
        ->assertNotFound();
});
