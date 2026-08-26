<?php

use App\Domain\Collection\Enums\RiskLevel;
use App\Domain\Collection\Support\RiskCalculator;

function riskAsOf(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-08-26');
}

it('marks unpaid older than 60 days as critical', function () {
    $result = (new RiskCalculator)->assess([
        ['due_on' => '2026-06-20', 'amount' => 50_000, 'paid_amount' => 0],
    ], riskAsOf());

    expect($result['level'])->toBe(RiskLevel::Critical)
        ->and($result['days_overdue'])->toBe(67)
        ->and($result['calculator_version'])->toBe('collection-risk.v1');
});

it('marks unpaid older than 30 days with a weak on-time ratio as critical', function () {
    $result = (new RiskCalculator)->assess([
        ['due_on' => '2026-06-01', 'amount' => 50_000, 'paid_amount' => 50_000, 'last_paid_on' => '2026-07-01'],
        ['due_on' => '2026-07-15', 'amount' => 50_000, 'paid_amount' => 0],
    ], riskAsOf());

    expect($result['level'])->toBe(RiskLevel::Critical)
        ->and($result['days_overdue'])->toBe(42)
        ->and($result['on_time_ratio'])->toBeLessThan(0.5);
});

it('marks 31 to 60 days overdue with a solid history as high', function () {
    $result = (new RiskCalculator)->assess([
        ['due_on' => '2026-04-15', 'amount' => 50_000, 'paid_amount' => 50_000, 'last_paid_on' => '2026-04-10'],
        ['due_on' => '2026-05-15', 'amount' => 50_000, 'paid_amount' => 50_000, 'last_paid_on' => '2026-05-14'],
        ['due_on' => '2026-07-15', 'amount' => 50_000, 'paid_amount' => 0],
    ], riskAsOf());

    expect($result['level'])->toBe(RiskLevel::High)
        ->and($result['days_overdue'])->toBe(42)
        ->and($result['on_time_ratio'])->toBeGreaterThanOrEqual(0.5);
});

it('marks more than 15 days overdue with two recent lates as high', function () {
    $result = (new RiskCalculator)->assess([
        ['due_on' => '2026-05-01', 'amount' => 50_000, 'paid_amount' => 50_000, 'last_paid_on' => '2026-05-20'],
        ['due_on' => '2026-06-01', 'amount' => 50_000, 'paid_amount' => 50_000, 'last_paid_on' => '2026-06-20'],
        ['due_on' => '2026-08-01', 'amount' => 50_000, 'paid_amount' => 0],
    ], riskAsOf());

    expect($result['level'])->toBe(RiskLevel::High)
        ->and($result['days_overdue'])->toBe(25)
        ->and($result['late_last_4'])->toBeGreaterThanOrEqual(2);
});

it('marks 8 to 30 days overdue as medium', function () {
    $result = (new RiskCalculator)->assess([
        ['due_on' => '2026-08-10', 'amount' => 50_000, 'paid_amount' => 0],
    ], riskAsOf());

    expect($result['level'])->toBe(RiskLevel::Medium)
        ->and($result['days_overdue'])->toBe(16);
});

it('marks one late among the last three as medium', function () {
    $result = (new RiskCalculator)->assess([
        ['due_on' => '2026-06-01', 'amount' => 50_000, 'paid_amount' => 50_000, 'last_paid_on' => '2026-06-15'],
        ['due_on' => '2026-07-01', 'amount' => 50_000, 'paid_amount' => 50_000, 'last_paid_on' => '2026-07-01'],
        ['due_on' => '2026-08-26', 'amount' => 50_000, 'paid_amount' => 0],
    ], riskAsOf());

    expect($result['level'])->toBe(RiskLevel::Medium)
        ->and($result['late_last_3'])->toBe(1)
        ->and($result['days_overdue'])->toBe(0);
});

it('marks a current unpaid installment as low', function () {
    $result = (new RiskCalculator)->assess([
        ['due_on' => '2026-09-15', 'amount' => 50_000, 'paid_amount' => 0],
    ], riskAsOf());

    expect($result['level'])->toBe(RiskLevel::Low)
        ->and($result['days_overdue'])->toBe(0)
        ->and($result['outstanding_amount'])->toBe(50_000);
});
