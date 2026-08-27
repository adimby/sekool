<?php

use App\Domain\Reliability\Support\SchoolReliabilityCalculator;

it('starts at 70 and adds capped operational facts', function () {
    $calculator = new SchoolReliabilityCalculator;

    $result = $calculator->compute([
        ['event_type' => 'invoice_issued'],
        ['event_type' => 'invoice_issued'],
        ['event_type' => 'payment_recorded'],
        ['event_type' => 'family_contacted'],
        ['event_type' => 'school_responded_within_sla'],
        ['event_type' => 'ignored_noise'],
    ]);

    expect($result['value'])->toBe(70 + 8 + 6 + 3 + 5)
        ->and($result['band'])->toBe('high')
        ->and($result['calculator_version'])->toBe('school-reliability.v1')
        ->and($result['event_count'])->toBe(5)
        ->and($result['factors'])->toHaveCount(4);
});

it('caps invoice contribution and is reproducible', function () {
    $calculator = new SchoolReliabilityCalculator;
    $events = array_fill(0, 10, ['event_type' => 'invoice_issued']);

    $first = $calculator->compute($events);
    $second = $calculator->compute($events);

    expect($first['value'])->toBe(86)
        ->and($first['inputs_digest'])->toBe($second['inputs_digest'])
        ->and($first['factors'][0]['contribution'])->toBe(16);
});
