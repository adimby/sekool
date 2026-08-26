<?php

namespace App\Domain\Reliability\Actions;

use App\Domain\Reliability\Models\ReliabilityScore;
use App\Domain\Reliability\Models\ReliabilityScoreFactor;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\Reliability\Support\FamilyReliabilityCalculator;

final class ComputeFamilyReliability
{
    public function __construct(private readonly FamilyReliabilityCalculator $calculator) {}

    public function execute(string $schoolId, string $familyId): ReliabilityScore
    {
        $events = TrustEvent::query()
            ->where('subject_type', 'family')
            ->where('subject_id', $familyId)
            ->where(function ($query) use ($schoolId): void {
                $query->where('school_id', $schoolId)->orWhereNull('school_id');
            })
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (TrustEvent $event): array => ['event_type' => $event->event_type])
            ->all();

        $computed = $this->calculator->compute($events);

        $score = ReliabilityScore::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'subject_type' => 'family',
                'subject_id' => $familyId,
                'index_type' => 'family',
            ],
            [
                'value' => $computed['value'],
                'band' => $computed['band'],
                'calculator_version' => $computed['calculator_version'],
                'computed_at' => now(),
                'inputs_digest' => $computed['inputs_digest'],
                'event_count' => $computed['event_count'],
            ],
        );

        ReliabilityScoreFactor::query()->where('score_id', $score->id)->delete();
        foreach ($computed['factors'] as $factor) {
            ReliabilityScoreFactor::query()->create([
                'score_id' => $score->id,
                'event_type' => $factor['event_type'],
                'human_label' => $factor['human_label'],
                'contribution' => $factor['contribution'],
                'event_count' => $factor['event_count'],
            ]);
        }

        return $score->refresh()->load('factors');
    }
}
