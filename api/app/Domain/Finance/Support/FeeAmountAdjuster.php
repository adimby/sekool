<?php

namespace App\Domain\Finance\Support;

use App\Domain\Finance\Enums\FeeAdjustmentType;
use App\Domain\Platform\Exceptions\DomainException;

final class FeeAmountAdjuster
{
    public static function apply(int $amount, FeeAdjustmentType $type, int $amountDelta, int $percentBps): int
    {
        $adjusted = match ($type) {
            FeeAdjustmentType::Amount => $amount + $amountDelta,
            FeeAdjustmentType::Percent => self::applyPercent($amount, $percentBps),
        };

        if ($adjusted <= 0) {
            throw new DomainException('Le montant ajusté doit rester strictement positif.');
        }

        return $adjusted;
    }

    /**
     * percentBps is hundredths of a percent: 500 = +5.00 %, -250 = −2.50 %.
     * Rounding is half-up in integer Ariary.
     */
    public static function applyPercent(int $amount, int $percentBps): int
    {
        $numerator = $amount * (10_000 + $percentBps);

        if ($numerator >= 0) {
            return intdiv($numerator + 5_000, 10_000);
        }

        return intdiv($numerator - 5_000, 10_000);
    }

    public static function percentToBps(float|int|string $percent): int
    {
        return (int) round(((float) $percent) * 100);
    }
}
