<?php

namespace App\Domain\Reliability\Actions;

use App\Domain\Reliability\Models\ReliabilityScore;
use App\Domain\Reliability\Models\ReliabilityScoreFactor;

final class PersistReliabilityScore
{
    /**
     * @param  array{value: int, band: string, calculator_version: string, event_count: int, inputs_digest: string, factors: list<array{event_type: string, human_label: string, contribution: int, event_count: int}>}  $computed
     */
    public function execute(
        string $schoolId,
        string $subjectType,
        string $subjectId,
        string $indexType,
        array $computed,
    ): ReliabilityScore {
        $score = ReliabilityScore::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'index_type' => $indexType,
                'calculator_version' => $computed['calculator_version'],
            ],
            [
                'value' => $computed['value'],
                'band' => $computed['band'],
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
