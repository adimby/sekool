<?php

use App\Domain\SchoolKit\Models\KitDefinition;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

it('never lets school A read school B kit catalogs or orders', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($a);

    $definitionId = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/kit-definitions", [
            'school_year_id' => $a['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => 'Kit A',
            'supplier_name' => 'Fournisseur A',
            'packs' => [['tier' => 'eco', 'total_amount' => 10_000]],
        ])
        ->assertCreated()
        ->json('data.id');

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    expect(KitDefinition::query()->pluck('id'))->not->toContain($definitionId);
    $ids = collect(DB::select('select id from kit_definitions'))->pluck('id');
    expect($ids)->not->toContain($definitionId);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/kit-definitions")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/kit-definitions")
        ->assertNotFound();
});
