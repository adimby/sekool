<?php

namespace App\Domain\Reliability\Support;

/**
 * School↔family relationship health. Uninstrumented channels (print, unknown) are excluded (G-07).
 * A number is not displayable below MIN_EVENTS included facts.
 */
final class RelationshipHealthCalculator
{
    public const VERSION = 'relationship-health.v1';

    public const MIN_EVENTS = 5;

    /** @var list<string> */
    public const EXCLUDED_TYPES = [
        'message_uninstrumented',
        'unknown',
        'queued',
        'ready_to_print',
        'print',
        'message_unknown',
        'message_queued',
        'message_failed',
    ];

    /** @var array<string, array{per: int, cap: int, label: string}> */
    private const WEIGHTS = [
        'message_delivered' => ['per' => 4, 'cap' => 20, 'label' => 'Messages remis (canal instrumenté)'],
        'message_read' => ['per' => 6, 'cap' => 18, 'label' => 'Messages lus'],
        'message_answered' => ['per' => 8, 'cap' => 16, 'label' => 'Réponses de la famille'],
        'document_provided' => ['per' => 5, 'cap' => 15, 'label' => 'Documents remis'],
        'school_responded_within_sla' => ['per' => 5, 'cap' => 15, 'label' => 'Réponse de l’école sous 48 h'],
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
            if (in_array($type, self::EXCLUDED_TYPES, true) || ! isset(self::WEIGHTS[$type])) {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $included = array_sum($counts);
        $value = 60;
        $factors = [];
        foreach (self::WEIGHTS as $type => $weight) {
            $n = $counts[$type] ?? 0;
            if ($n === 0) {
                continue;
            }
            $contribution = min($weight['cap'], $n * $weight['per']);
            $value += $contribution;
            $factors[] = [
                'event_type' => $type,
                'human_label' => $weight['label'],
                'contribution' => $contribution,
                'event_count' => $n,
            ];
        }

        $value = max(0, min(100, $value));
        ksort($counts);

        if ($included < self::MIN_EVENTS) {
            $band = 'insufficient';
        } else {
            $band = $value >= 80 ? 'high' : ($value >= 50 ? 'medium' : 'low');
        }

        return [
            'value' => $value,
            'band' => $band,
            'calculator_version' => self::VERSION,
            'event_count' => $included,
            'inputs_digest' => hash('sha256', json_encode($counts).self::VERSION),
            'factors' => $factors,
        ];
    }
}
