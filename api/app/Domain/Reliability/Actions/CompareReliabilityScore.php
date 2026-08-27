<?php

namespace App\Domain\Reliability\Actions;

use App\Domain\Collection\Support\CollectionPayload;
use App\Domain\Reliability\Models\ReliabilityScore;

final class CompareReliabilityScore
{
    /**
     * @param  array{value: int, band: string, calculator_version: string, event_count: int, inputs_digest: string, factors: list<array{event_type: string, human_label: string, contribution: int, event_count: int}>}  $recomputed
     * @return array{stored: array<string, mixed>, recomputed: array<string, mixed>, digest_match: bool, version_match: bool}
     */
    public function execute(ReliabilityScore $stored, array $recomputed): array
    {
        return [
            'stored' => CollectionPayload::reliability($stored->load('factors')),
            'recomputed' => CollectionPayload::reliabilityComputed(
                $recomputed,
                (string) $stored->subject_type,
                (string) $stored->subject_id,
                (string) $stored->index_type,
            ),
            'digest_match' => hash_equals((string) $stored->inputs_digest, $recomputed['inputs_digest']),
            'version_match' => $stored->calculator_version === $recomputed['calculator_version'],
        ];
    }
}
