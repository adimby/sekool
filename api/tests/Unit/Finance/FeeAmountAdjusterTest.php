<?php

use App\Domain\Finance\Enums\FeeAdjustmentType;
use App\Domain\Finance\Support\FeeAmountAdjuster;
use App\Domain\Platform\Exceptions\DomainException;

it('adds a fixed Ariary difference to a line', function () {
    expect(FeeAmountAdjuster::apply(50_000, FeeAdjustmentType::Amount, 5_000, 0))->toBe(55_000)
        ->and(FeeAmountAdjuster::apply(50_000, FeeAdjustmentType::Amount, -10_000, 0))->toBe(40_000);
});

it('applies a percentage with half-up integer rounding', function () {
    expect(FeeAmountAdjuster::percentToBps(10))->toBe(1_000)
        ->and(FeeAmountAdjuster::percentToBps(5.25))->toBe(525)
        ->and(FeeAmountAdjuster::apply(50_000, FeeAdjustmentType::Percent, 0, 1_000))->toBe(55_000)
        ->and(FeeAmountAdjuster::apply(50_000, FeeAdjustmentType::Percent, 0, 525))->toBe(52_625);
});

it('refuses an adjustment that would zero or invert a line', function () {
    FeeAmountAdjuster::apply(50_000, FeeAdjustmentType::Amount, -50_000, 0);
})->throws(DomainException::class);
