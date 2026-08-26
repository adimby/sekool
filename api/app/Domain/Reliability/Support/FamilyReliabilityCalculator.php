<?php

namespace App\Domain\Reliability\Support;

/**
 * Family Reliability from TrustEvents. Never used by authorization.
 */
final class FamilyReliabilityCalculator
{
    public const VERSION = 'family-reliability.v1';

    /**
     * @param  list<array{event_type: string}>  $events
     * @return array{value: int, band: string, calculator_version: string, event_count: int, inputs_digest: string, factors: list<array{event_type: string, human_label: string, contribution: int, event_count: int}>}
     */
    public function compute(array $events): array
    {
        $counts = [];
        foreach ($events as $event) {
            $type = $event['event_type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $value = 70;
        $factors = [];

        $onTime = $counts['payment_on_time'] ?? 0;
        $late = $counts['payment_late'] ?? 0;
        if ($onTime > 0) {
            $contribution = min(24, $onTime * 8);
            $value += $contribution;
            $factors[] = [
                'event_type' => 'payment_on_time',
                'human_label' => 'Paiements à l’échéance',
                'contribution' => $contribution,
                'event_count' => $onTime,
            ];
        }
        if ($late > 0) {
            $contribution = min(36, $late * 12);
            $value -= $contribution;
            $factors[] = [
                'event_type' => 'payment_late',
                'human_label' => 'Paiements après l’échéance',
                'contribution' => -$contribution,
                'event_count' => $late,
            ];
        }

        $value = max(0, min(100, $value));
        $band = $value >= 80 ? 'high' : ($value >= 50 ? 'medium' : 'low');
        ksort($counts);

        return [
            'value' => $value,
            'band' => $band,
            'calculator_version' => self::VERSION,
            'event_count' => count($events),
            'inputs_digest' => hash('sha256', json_encode($counts).self::VERSION),
            'factors' => $factors,
        ];
    }
}
