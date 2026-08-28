<?php

use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Models\Receipt;
use App\Domain\Platform\Tenancy\TenantContext;

it('refuses a discount without a reason', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $this->provisionFeeSchedule($school);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/enrollments/{$family['enrollment']->id}/invoices", [
            'discount_amount' => 10_000,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Une remise exige un motif.');
});

it('applies a motivated discount to the invoice net amount', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $this->provisionFeeSchedule($school);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/enrollments/{$family['enrollment']->id}/invoices", [
            'discount_amount' => 10_000,
            'discount_reason' => 'Remise fratrie',
        ])
        ->assertCreated()
        ->assertJsonPath('data.net_amount', 140_000)
        ->assertJsonPath('data.discount_reason', 'Remise fratrie');
});

it('is idempotent for the same payment key', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $invoice = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->json('data');

    $payload = [
        'invoice_id' => $invoice['id'],
        'amount' => 50_000,
        'method' => 'cash',
        'received_on' => '2026-09-10',
        'idempotency_key' => '33333333-3333-4333-8333-333333333333',
    ];

    $first = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/payments", $payload)
        ->assertCreated()
        ->json('data');

    $second = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/payments", $payload)
        ->assertOk()
        ->json('data');

    expect($second['id'])->toBe($first['id'])
        ->and($second['receipt']['number'])->toBe($first['receipt']['number']);

    TenantContext::runWithRlsBypass(fn () => expect(Payment::query()->withoutGlobalScopes()->count())->toBe(1));
});

it('allocates gapless receipt numbers per school year', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $invoice = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->json('data');

    $first = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/payments", [
            'invoice_id' => $invoice['id'],
            'amount' => 50_000,
            'method' => 'cash',
            'received_on' => '2026-09-10',
        ])
        ->json('data.receipt.number');

    $second = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/payments", [
            'invoice_id' => $invoice['id'],
            'amount' => 50_000,
            'method' => 'mobile_money',
            'received_on' => '2026-09-11',
        ])
        ->json('data.receipt.number');

    expect($first)->toBe('REC-2026-000001')
        ->and($second)->toBe('REC-2026-000002');

    TenantContext::runWithRlsBypass(fn () => expect(Receipt::query()->withoutGlobalScopes()->count())->toBe(2));
});

it('exports recorded payments as CSV', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $invoice = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/payments", [
            'invoice_id' => $invoice['id'],
            'amount' => 50_000,
            'method' => 'cash',
            'received_on' => '2026-09-10',
        ]);

    $csv = $this->actingAs($school['account'], 'sanctum')
        ->get("/api/v1/schools/{$schoolId}/payments/export")
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('REC-2026-000001')
        ->and($csv)->toContain('50000');
});
