<?php

use App\Domain\Finance\Models\Payment;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\Reliability\Models\TrustEvent;

it('runs the school-core cycle: class, attendance, invoice, partial payment, parent balance', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])
        ->assertCreated()
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ])
        ->assertOk()
        ->assertJsonPath('data.classroom_id', $classroom['id']);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => '2026-09-15',
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
                'client_reference' => '11111111-1111-4111-8111-111111111111',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.status', 'present');

    $invoice = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->assertCreated()
        ->json('data');

    expect($invoice['net_amount'])->toBe(150_000)
        ->and($invoice['number'])->toStartWith('FAC-2026-')
        ->and($invoice['installments'])->toHaveCount(3)
        ->and($invoice['remaining_amount'])->toBe(150_000);

    $payment = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/payments", [
            'invoice_id' => $invoice['id'],
            'amount' => 50_000,
            'method' => 'cash',
            'received_on' => '2026-09-10',
            'idempotency_key' => '22222222-2222-4222-8222-222222222222',
        ])
        ->assertCreated()
        ->json();

    expect($payment['data']['amount'])->toBe(50_000)
        ->and($payment['data']['receipt']['number'])->toStartWith('REC-2026-')
        ->and($payment['invoice']['remaining_amount'])->toBe(100_000)
        ->and($payment['invoice']['status'])->toBe('partially_paid');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/finance")
        ->assertOk()
        ->assertJsonPath('remaining_amount', 100_000)
        ->assertJsonPath('data.0.invoice.number', $invoice['number'])
        ->assertJsonPath('data.0.payments.0.receipt_number', $payment['data']['receipt']['number']);

    TenantContext::runWithRlsBypass(function (): void {
        expect(TrustEvent::query()->where('event_type', 'payment_on_time')->count())->toBe(1)
            ->and(Payment::query()->withoutGlobalScopes()->count())->toBe(1);
    });
});

it('replays attendance with the same client_reference without duplicating', function () {
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

    $payload = [
        'date' => '2026-09-16',
        'session' => 'full_day',
        'records' => [[
            'enrollment_id' => $family['enrollment']->id,
            'status' => 'absent',
            'client_reference' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        ]],
    ];

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", $payload)
        ->assertCreated();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", $payload)
        ->assertCreated();

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/attendance?".http_build_query([
            'classroom_id' => $classroom['id'],
            'date' => '2026-09-16',
            'session' => 'full_day',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attendance.status', 'absent');
});
