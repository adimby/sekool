<?php

namespace App\Domain\Reliability\Actions;

use App\Domain\Reliability\Models\ReliabilityScore;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\Reliability\Support\RelationshipHealthCalculator;
use App\Domain\Reliability\Support\ReliabilityIndexes;

final class ComputeRelationshipHealth
{
    public function __construct(
        private readonly RelationshipHealthCalculator $calculator,
        private readonly PersistReliabilityScore $persist,
    ) {}

    /**
     * @return array{value: int, band: string, calculator_version: string, event_count: int, inputs_digest: string, factors: list<array{event_type: string, human_label: string, contribution: int, event_count: int}>}
     */
    public function preview(string $schoolId, string $familyId): array
    {
        $events = TrustEvent::query()
            ->where('subject_type', ReliabilityIndexes::SUBJECT_RELATIONSHIP)
            ->where('subject_id', $familyId)
            ->where('school_id', $schoolId)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (TrustEvent $event): array => ['event_type' => $event->event_type])
            ->all();

        return $this->calculator->compute($events);
    }

    public function execute(string $schoolId, string $familyId): ReliabilityScore
    {
        return $this->persist->execute(
            $schoolId,
            ReliabilityIndexes::SUBJECT_RELATIONSHIP,
            $familyId,
            ReliabilityIndexes::RELATIONSHIP,
            $this->preview($schoolId, $familyId),
        );
    }
}
