<?php

use App\Domain\Reliability\Support\FamilyReliabilityCalculator;

it('starts at 70 and moves by on-time and late payments', function () {
    $calculator = new FamilyReliabilityCalculator;

    $result = $calculator->compute([
        ['event_type' => 'payment_on_time'],
        ['event_type' => 'payment_on_time'],
        ['event_type' => 'payment_late'],
    ]);

    expect($result['value'])->toBe(70 + 16 - 12)
        ->and($result['band'])->toBe('medium')
        ->and($result['calculator_version'])->toBe('family-reliability.v1')
        ->and($result['factors'])->toHaveCount(2);
});

it('clamps between 0 and 100', function () {
    $calculator = new FamilyReliabilityCalculator;
    $late = array_fill(0, 10, ['event_type' => 'payment_late']);

    expect($calculator->compute($late)['value'])->toBe(34)
        ->and($calculator->compute($late)['band'])->toBe('low');
});
