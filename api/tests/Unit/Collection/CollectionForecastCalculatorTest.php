<?php

use App\Domain\Collection\Support\CollectionForecastCalculator;

it('applies empirical Bayes smoothing with k=5 and stays decomposable', function () {
    $calculator = new CollectionForecastCalculator;

    $result = $calculator->forecast([
        ['remaining' => 100_000, 'family_on_time_ratio' => 1.0],
        ['remaining' => 50_000, 'family_on_time_ratio' => null],
    ], 0.6);

    $pKnown = ((1.0 * 5) + 0.6) / 6;
    $pKnown = max(0.05, min(0.95, $pKnown));
    $expected = (int) round($pKnown * 100_000 + 0.6 * 50_000);

    expect($result['method_version'])->toBe('collection-forecast.v1')
        ->and($result['k'])->toBe(5)
        ->and($result['expected_amount'])->toBe($expected)
        ->and($result['confidence_low_amount'])->toBe((int) round($expected * 0.8))
        ->and($result['confidence_high_amount'])->toBe((int) round($expected * 1.2));
});
