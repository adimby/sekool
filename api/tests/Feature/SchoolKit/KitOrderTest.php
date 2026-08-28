<?php

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\SchoolYear;
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
            'price_source' => 'supplier',
            'supplier_name' => 'Librairie Analakely',
            'commission_rate_bps' => 250,
            'needs' => [[
                'label' => 'Cahier 200 pages',
                'quantity' => 3,
                'offers' => [
                    ['tier' => 'eco', 'brand' => 'Oxford', 'unit_amount' => 15_000],
                    ['tier' => 'standard', 'brand' => 'Clairefontaine', 'unit_amount' => 24_000],
                    ['tier' => 'luxe', 'brand' => 'Rhodia', 'unit_amount' => 32_000],
                ],
            ]],
        ])
        ->assertCreated()
        ->json('data');

    $eco = collect($catalog['packs'])->firstWhere('tier', 'eco');
    expect($eco['pay_instruction'])->toContain('FANABE n’encaisse pas')
        ->and($eco['tier_label'])->toBe('Éco')
        ->and($eco['total_amount'])->toBe(45_000)
        ->and($catalog['needs'][0]['label'])->toBe('Cahier 200 pages')
        ->and($catalog['needs'][0]['quantity'])->toBe(3)
        ->and($catalog['needs'][0]['offers'][0]['brand'])->toBe('Oxford')
        ->and(collect($catalog['packs'])->firstWhere('tier', 'premium')['tier_label'])->toBe('Luxe');

    $order = $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/parent/kit-orders', [
            'enrollment_id' => $family['enrollment']->id,
            'fulfillment' => 'partner',
            'kit_pack_id' => $eco['id'],
        ])
        ->assertCreated()
        ->json('data');

    expect($order['total_amount'])->toBe(45_000)
        ->and($order['commission_amount'])->toBe(1_125)
        ->and($order['fulfillment'])->toBe('partner')
        ->and($order['pay_instruction'])->toBe(KitCopy::payAtSupplier('Librairie Analakely'))
        ->and($order['status'])->toBe('submitted');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/parent/kit-orders', [
            'enrollment_id' => $family['enrollment']->id,
            'kit_pack_id' => $eco['id'],
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

it('lets a parent supply the list themselves instead of ordering a pack', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $catalog = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'supplier_name' => 'Librairie Analakely',
            'needs' => [[
                'label' => 'Stylos',
                'quantity' => 6,
                'offers' => [
                    ['tier' => 'eco', 'brand' => 'BIC', 'unit_amount' => 200],
                ],
            ]],
        ])
        ->assertCreated()
        ->json('data');

    $choice = $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/parent/kit-orders', [
            'enrollment_id' => $family['enrollment']->id,
            'fulfillment' => 'self',
            'kit_definition_id' => $catalog['id'],
        ])
        ->assertCreated()
        ->json('data');

    expect($choice['fulfillment'])->toBe('self')
        ->and($choice['status'])->toBe('self_supplied')
        ->and($choice['total_amount'])->toBe(0)
        ->and($choice['kit_pack_id'])->toBeNull()
        ->and($choice['pay_instruction'])->toContain('fournit');
});

it('lets the class teacher publish the supply list for their grade only', function () {
    $school = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $otherGrade = null;
    TenantContext::runWithRlsBypass(function () use ($school, &$otherGrade): void {
        $otherGrade = GradeLevel::query()->create([
            'school_id' => $school['school']->id,
            'name' => '5ème',
            'stage' => GradeStage::Middle,
            'sequence' => 5,
        ]);
    });

    $teacher = $this->provisionTeacher($school, $classroom['id']);

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'supplier_name' => 'Librairie Analakely',
            'needs' => [[
                'label' => 'Cahier',
                'quantity' => 4,
                'offers' => [['tier' => 'eco', 'brand' => 'Oxford', 'unit_amount' => 2_000]],
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.needs.0.label', 'Cahier');

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $otherGrade->id,
            'supplier_name' => 'Librairie Analakely',
            'needs' => [[
                'label' => 'Cahier',
                'quantity' => 1,
                'offers' => [['tier' => 'eco', 'brand' => 'Oxford', 'unit_amount' => 2_000]],
            ]],
        ])
        ->assertForbidden();
});

it('copies last year’s supply list onto the new year', function () {
    $school = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'supplier_name' => 'Librairie Analakely',
            'needs' => [[
                'label' => 'Cahier 200 pages',
                'quantity' => 4,
                'offers' => [
                    ['tier' => 'eco', 'brand' => 'Oxford', 'unit_amount' => 4_000],
                    ['tier' => 'standard', 'brand' => 'Clairefontaine', 'unit_amount' => 6_500],
                ],
            ]],
        ])
        ->assertCreated();

    $nextYear = null;
    TenantContext::runWithRlsBypass(function () use ($school, &$nextYear): void {
        $nextYear = SchoolYear::factory()->create([
            'school_id' => $school['school']->id,
            'label' => '2027-2028',
            'is_current' => false,
        ]);
    });

    $copied = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions/copy-year", [
            'from_year_id' => $school['year']->id,
            'to_year_id' => $nextYear->id,
        ])
        ->assertCreated()
        ->json('data');

    expect($copied)->toHaveCount(1)
        ->and($copied[0]['needs'][0]['label'])->toBe('Cahier 200 pages')
        ->and($copied[0]['needs'][0]['offers'][0]['brand'])->toBe('Oxford')
        ->and($copied[0]['copied_from_id'])->not->toBeNull()
        ->and($copied[0]['school_year_id'])->toBe($nextYear->id);
});

it('refuses a pack from another grade for the enrolled student', function () {
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

    TenantContext::runWithRlsBypass(function () use ($family, $classroom): void {
        $family['enrollment']->forceFill(['classroom_id' => $classroom['id']])->save();
    });

    $otherGrade = null;
    TenantContext::runWithRlsBypass(function () use ($school, &$otherGrade): void {
        $otherGrade = GradeLevel::query()->create([
            'school_id' => $school['school']->id,
            'name' => '5ème',
            'stage' => GradeStage::Middle,
            'sequence' => 5,
        ]);
    });

    $otherList = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $otherGrade->id,
            'supplier_name' => 'Librairie Analakely',
            'needs' => [[
                'label' => 'Compas',
                'quantity' => 1,
                'offers' => [['tier' => 'eco', 'brand' => 'Maped', 'unit_amount' => 8_000]],
            ]],
        ])
        ->assertCreated()
        ->json('data');

    $eco = collect($otherList['packs'])->firstWhere('tier', 'eco');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/parent/kit-orders', [
            'enrollment_id' => $family['enrollment']->id,
            'fulfillment' => 'partner',
            'kit_pack_id' => $eco['id'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Cette liste ne correspond pas au niveau de l’élève.');
});

it('lets the class teacher copy last year’s list for their grade only', function () {
    $school = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $otherGrade = null;
    TenantContext::runWithRlsBypass(function () use ($school, &$otherGrade): void {
        $otherGrade = GradeLevel::query()->create([
            'school_id' => $school['school']->id,
            'name' => '5ème',
            'stage' => GradeStage::Middle,
            'sequence' => 5,
        ]);
    });

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'supplier_name' => 'Librairie Analakely',
            'needs' => [[
                'label' => 'Cahier 6ème',
                'quantity' => 4,
                'offers' => [['tier' => 'eco', 'brand' => 'Oxford', 'unit_amount' => 4_000]],
            ]],
        ])
        ->assertCreated();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $otherGrade->id,
            'supplier_name' => 'Librairie Analakely',
            'needs' => [[
                'label' => 'Cahier 5ème',
                'quantity' => 6,
                'offers' => [['tier' => 'eco', 'brand' => 'Oxford', 'unit_amount' => 4_000]],
            ]],
        ])
        ->assertCreated();

    $nextYear = null;
    TenantContext::runWithRlsBypass(function () use ($school, &$nextYear): void {
        $nextYear = SchoolYear::factory()->create([
            'school_id' => $school['school']->id,
            'label' => '2027-2028',
            'is_current' => false,
        ]);
    });

    $teacher = $this->provisionTeacher($school, $classroom['id']);

    $copied = $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/kit-definitions/copy-year", [
            'from_year_id' => $school['year']->id,
            'to_year_id' => $nextYear->id,
        ])
        ->assertCreated()
        ->json('data');

    expect($copied)->toHaveCount(1)
        ->and($copied[0]['grade_level_id'])->toBe($core['grade']->id)
        ->and($copied[0]['needs'][0]['label'])->toBe('Cahier 6ème');
});
