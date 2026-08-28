<?php

namespace App\Domain\Reliability\Support;

/**
 * School Reliability from TrustEvents. Visible only to that school. Never used by authorization.
 */
final class SchoolReliabilityCalculator
{
    public const VERSION = 'school-reliability.v1';

    /** @var array<string, array{per: int, cap: int, label: string, sign: int}> */
    private const WEIGHTS = [
        'invoice_issued' => ['per' => 4, 'cap' => 16, 'label' => 'Factures émises', 'sign' => 1],
        'payment_recorded' => ['per' => 6, 'cap' => 24, 'label' => 'Paiements enregistrés', 'sign' => 1],
        'family_contacted' => ['per' => 3, 'cap' => 12, 'label' => 'Familles contactées', 'sign' => 1],
        'school_responded_within_sla' => ['per' => 5, 'cap' => 15, 'label' => 'Réponse de l’école sous 48 h', 'sign' => 1],
    ];

    /**
     * @param  list<array{event_type: string}>  $events
     * @return array{value: int, band: string, calculator_version: string, event_count: int, inputs_digest: string, factors: list<array{event_type: string, human_label: string, contribution: int, event_count: int}>}
     */
    public function compute(array $events): array
    {
        $counts = [];
        foreach ($events as $event) {
            $type = $event['event_type'];
            if (! isset(self::WEIGHTS[$type])) {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $value = 70;
        $factors = [];
        foreach (self::WEIGHTS as $type => $weight) {
            $n = $counts[$type] ?? 0;
            if ($n === 0) {
                continue;
            }
            $contribution = min($weight['cap'], $n * $weight['per']) * $weight['sign'];
            $value += $contribution;
            $factors[] = [
                'event_type' => $type,
                'human_label' => $weight['label'],
                'contribution' => $contribution,
                'event_count' => $n,
            ];
        }

        $value = max(0, min(100, $value));
        $band = $value >= 80 ? 'high' : ($value >= 50 ? 'medium' : 'low');
        ksort($counts);

        return [
            'value' => $value,
            'band' => $band,
            'calculator_version' => self::VERSION,
            'event_count' => array_sum($counts),
            'inputs_digest' => hash('sha256', json_encode($counts).self::VERSION),
            'factors' => $factors,
        ];
    }
}
