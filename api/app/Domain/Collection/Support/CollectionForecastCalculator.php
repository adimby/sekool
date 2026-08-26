<?php

namespace App\Domain\Collection\Support;

/**
 * Weekly collection forecast, A-05 empirical Bayes. No black-box inference.
 */
final class CollectionForecastCalculator
{
    public const VERSION = 'collection-forecast.v1';

    public const SMOOTHING_K = 5;

    /**
     * @param  list<array{remaining: int, family_on_time_ratio: float|null}>  $dueThisWeek
     * @return array{expected_amount: int, confidence_low_amount: int, confidence_high_amount: int, method_version: string, k: int, school_on_time_ratio: float}
     */
    public function forecast(array $dueThisWeek, float $schoolOnTimeRatio): array
    {
        $expected = 0.0;
        foreach ($dueThisWeek as $row) {
            $family = $row['family_on_time_ratio'];
            $p = $family === null
                ? $schoolOnTimeRatio
                : (($family * self::SMOOTHING_K) + $schoolOnTimeRatio) / (self::SMOOTHING_K + 1);
            $p = max(0.05, min(0.95, $p));
            $expected += $p * $row['remaining'];
        }

        $expectedInt = (int) round($expected);
        $spread = (int) round($expectedInt * 0.2);

        return [
            'expected_amount' => $expectedInt,
            'confidence_low_amount' => max(0, $expectedInt - $spread),
            'confidence_high_amount' => $expectedInt + $spread,
            'method_version' => self::VERSION,
            'k' => self::SMOOTHING_K,
            'school_on_time_ratio' => round($schoolOnTimeRatio, 4),
        ];
    }
}
