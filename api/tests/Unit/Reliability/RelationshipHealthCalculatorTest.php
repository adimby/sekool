<?php

use App\Domain\Reliability\Support\RelationshipHealthCalculator;

it('starts at 60 and ignores uninstrumented channels (G-07)', function () {
    $calculator = new RelationshipHealthCalculator;

    $result = $calculator->compute([
        ['event_type' => 'message_delivered'],
        ['event_type' => 'message_delivered'],
        ['event_type' => 'message_delivered'],
        ['event_type' => 'message_delivered'],
        ['event_type' => 'message_delivered'],
        ['event_type' => 'message_uninstrumented'],
        ['event_type' => 'unknown'],
        ['event_type' => 'ready_to_print'],
        ['event_type' => 'print'],
        ['event_type' => 'queued'],
    ]);

    expect($result['event_count'])->toBe(5)
        ->and($result['value'])->toBe(80)
        ->and($result['band'])->toBe('high')
        ->and($result['calculator_version'])->toBe('relationship-health.v1');
});

it('does not treat muted channels as a bad index', function () {
    $calculator = new RelationshipHealthCalculator;
    $muted = array_fill(0, 12, ['event_type' => 'message_uninstrumented']);

    $result = $calculator->compute($muted);

    expect($result['event_count'])->toBe(0)
        ->and($result['value'])->toBe(60)
        ->and($result['band'])->toBe('insufficient')
        ->and($result['factors'])->toBeEmpty();
});

it('hides judgment when there are fewer than five included facts', function () {
    $calculator = new RelationshipHealthCalculator;

    $four = $calculator->compute(array_fill(0, 4, ['event_type' => 'message_delivered']));

    expect($four['event_count'])->toBe(4)
        ->and($four['value'])->toBe(76)
        ->and($four['band'])->toBe('insufficient');
});
