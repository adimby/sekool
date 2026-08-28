<?php

use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\SchoolKit\Models\KitOrder;
use App\Domain\SchoolKit\Support\KitCopy;
use Illuminate\Support\Facades\Route;

it('lets a parent order a kit paid at the supplier, never through FANABE', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $catalog = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => 'Kit 6ème',
            'needs' => [['label' => 'Cahier 200 pages', 'quantity' => 3]],
            'supplier_name' => 'Librairie Analakely',
            'commission_rate_bps' => 250,
            'packs' => [
                ['tier' => 'eco', 'total_amount' => 45_000],
                ['tier' => 'standard', 'total_amount' => 72_000],
            ],
        ])
        ->assertCreated()
        ->json('data');

    $ecoId = collect($catalog['packs'])->firstWhere('tier', 'eco')['id'];
    expect($catalog['packs'][0]['pay_instruction'])->toContain('FANABE n’encaisse pas')
        ->and($catalog['needs'][0]['label'])->toBe('Cahier 200 pages')
        ->and($catalog['needs'][0]['quantity'])->toBe(3);

    $order = $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/parent/kit-orders', [
            'enrollment_id' => $family['enrollment']->id,
            'kit_pack_id' => $ecoId,
        ])
        ->assertCreated()
        ->json('data');

    expect($order['total_amount'])->toBe(45_000)
        ->and($order['commission_amount'])->toBe(1_125)
        ->and($order['pay_instruction'])->toBe(KitCopy::payAtSupplier('Librairie Analakely'))
        ->and($order['status'])->toBe('submitted');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/parent/kit-orders', [
            'enrollment_id' => $family['enrollment']->id,
            'kit_pack_id' => $ecoId,
        ])
        ->assertStatus(422);

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/kit-orders/{$order['id']}", [
            'status' => 'confirmed',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $payRoutes = collect(Route::getRoutes())->filter(function ($route): bool {
        $uri = $route->uri();

        return str_contains($uri, 'kit') && str_contains($uri, 'pay');
    });
    expect($payRoutes)->toBeEmpty();

    TenantContext::runWithRlsBypass(function () use ($order): void {
        $row = KitOrder::query()->withoutGlobalScopes()->find($order['id']);
        expect($row)->not->toBeNull()
            ->and($row->commission_amount)->toBe(1125);
    });
});
