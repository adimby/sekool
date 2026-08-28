<?php

namespace App\Domain\Reliability\Actions;

use App\Domain\Reliability\Models\ReliabilityScore;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\Reliability\Support\ReliabilityIndexes;
use App\Domain\Reliability\Support\SchoolReliabilityCalculator;

final class ComputeSchoolReliability
{
    public function __construct(
        private readonly SchoolReliabilityCalculator $calculator,
        private readonly PersistReliabilityScore $persist,
    ) {}

    /**
     * @return array{value: int, band: string, calculator_version: string, event_count: int, inputs_digest: string, factors: list<array{event_type: string, human_label: string, contribution: int, event_count: int}>}
     */
    public function preview(string $schoolId): array
    {
        $events = TrustEvent::query()
            ->where('subject_type', ReliabilityIndexes::SUBJECT_SCHOOL)
            ->where('subject_id', $schoolId)
            ->where('school_id', $schoolId)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (TrustEvent $event): array => ['event_type' => $event->event_type])
            ->all();

        return $this->calculator->compute($events);
    }

    public function execute(string $schoolId): ReliabilityScore
    {
        return $this->persist->execute(
            $schoolId,
            ReliabilityIndexes::SUBJECT_SCHOOL,
            $schoolId,
            ReliabilityIndexes::SCHOOL,
            $this->preview($schoolId),
        );
    }
}
