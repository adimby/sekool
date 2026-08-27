<?php

namespace App\Domain\Reliability\Actions;

use App\Domain\Reliability\Models\ReliabilityScore;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\Reliability\Support\FamilyReliabilityCalculator;
use App\Domain\Reliability\Support\ReliabilityIndexes;

final class ComputeFamilyReliability
{
    public function __construct(
        private readonly FamilyReliabilityCalculator $calculator,
        private readonly PersistReliabilityScore $persist,
    ) {}

    /**
     * @return array{value: int, band: string, calculator_version: string, event_count: int, inputs_digest: string, factors: list<array{event_type: string, human_label: string, contribution: int, event_count: int}>}
     */
    public function preview(string $schoolId, string $familyId): array
    {
        return $this->calculator->compute($this->events($schoolId, $familyId));
    }

    public function execute(string $schoolId, string $familyId): ReliabilityScore
    {
        return $this->persist->execute(
            $schoolId,
            ReliabilityIndexes::SUBJECT_FAMILY,
            $familyId,
            ReliabilityIndexes::FAMILY,
            $this->preview($schoolId, $familyId),
        );
    }

    /**
     * @return list<array{event_type: string}>
     */
    private function events(string $schoolId, string $familyId): array
    {
        return TrustEvent::query()
            ->where('subject_type', ReliabilityIndexes::SUBJECT_FAMILY)
            ->where('subject_id', $familyId)
            ->where(function ($query) use ($schoolId): void {
                $query->where('school_id', $schoolId)->orWhereNull('school_id');
            })
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (TrustEvent $event): array => ['event_type' => $event->event_type])
            ->all();
    }
}
