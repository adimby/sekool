<?php

namespace App\Domain\Collection\Support;

use App\Domain\Collection\Models\CollectionForecast;
use App\Domain\Collection\Models\CollectionTask;
use App\Domain\Collection\Models\RiskAssessment;
use App\Domain\Communication\Models\Message;
use App\Domain\Reliability\Models\ReliabilityScore;

final class CollectionPayload
{
    public static function task(CollectionTask $task): array
    {
        $person = $task->enrollment?->person;

        return [
            'id' => $task->id,
            'enrollment_id' => $task->enrollment_id,
            'template_key' => $task->template_key,
            'title' => $task->title,
            'reason_summary' => $task->reason_summary,
            'priority' => $task->priority,
            'status' => $task->status,
            'claimed_at' => $task->claimed_at?->toIso8601String(),
            'student' => $person === null ? null : [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
            ],
        ];
    }

    public static function assessment(RiskAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'enrollment_id' => $assessment->enrollment_id,
            'level' => $assessment->level->value,
            'effective_level' => $assessment->effectiveLevel()->value,
            'effective_label' => $assessment->effectiveLevel()->label(),
            'outstanding_amount' => $assessment->outstanding_amount,
            'days_overdue' => $assessment->days_overdue,
            'on_time_ratio' => $assessment->on_time_ratio,
            'calculator_version' => $assessment->calculator_version,
            'computed_at' => $assessment->computed_at?->toIso8601String(),
            'override' => $assessment->manual_override_level === null ? null : [
                'level' => $assessment->manual_override_level->value,
                'reason' => $assessment->override_reason,
                'until' => $assessment->override_until?->toIso8601String(),
            ],
            'factors' => $assessment->factors->map(fn ($factor): array => [
                'factor_key' => $factor->factor_key,
                'human_label' => $factor->human_label,
                'contribution' => $factor->contribution,
                'evidence' => $factor->evidence,
            ])->values()->all(),
        ];
    }

    public static function forecast(?CollectionForecast $forecast): ?array
    {
        if ($forecast === null) {
            return null;
        }

        return [
            'week_starting_on' => $forecast->week_starting_on->toDateString(),
            'expected_amount' => $forecast->expected_amount,
            'confidence_low_amount' => $forecast->confidence_low_amount,
            'confidence_high_amount' => $forecast->confidence_high_amount,
            'method_version' => $forecast->method_version,
            'computed_at' => $forecast->computed_at?->toIso8601String(),
            'breakdown' => $forecast->breakdown,
        ];
    }

    public static function message(Message $message, bool $staff = false): array
    {
        $payload = [
            'id' => $message->id,
            'channel' => $message->channel,
            'template_key' => $message->template_key,
            'subject' => $message->payload['subject'] ?? '',
            'body' => $message->payload['body'] ?? '',
            'queued_at' => $message->queued_at?->toIso8601String(),
            'sent_at' => $message->sent_at?->toIso8601String(),
        ];

        if ($staff) {
            $payload['recipient_person_id'] = $message->recipient_person_id;
            $payload['subject_person_id'] = $message->subject_person_id;
            $payload['priority'] = $message->priority;
        }

        return $payload;
    }

    public static function reliability(ReliabilityScore $score): array
    {
        $displayable = $score->band !== 'insufficient';

        return [
            'id' => $score->id,
            'subject_type' => $score->subject_type,
            'subject_id' => $score->subject_id,
            'index_type' => $score->index_type,
            'value' => $displayable ? $score->value : null,
            'band' => $score->band,
            'displayable' => $displayable,
            'calculator_version' => $score->calculator_version,
            'computed_at' => $score->computed_at?->toIso8601String(),
            'event_count' => $score->event_count,
            'minimum_events' => $score->index_type === 'relationship_health' ? 5 : null,
            'inputs_digest' => $score->inputs_digest,
            'factors' => $score->factors->map(fn ($factor): array => [
                'event_type' => $factor->event_type,
                'human_label' => $factor->human_label,
                'contribution' => $factor->contribution,
                'event_count' => $factor->event_count,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array{value: int, band: string, calculator_version: string, event_count: int, inputs_digest: string, factors: list<array{event_type: string, human_label: string, contribution: int, event_count: int}>}  $computed
     * @return array<string, mixed>
     */
    public static function reliabilityComputed(array $computed, string $subjectType, string $subjectId, string $indexType): array
    {
        $displayable = $computed['band'] !== 'insufficient';

        return [
            'id' => null,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'index_type' => $indexType,
            'value' => $displayable ? $computed['value'] : null,
            'band' => $computed['band'],
            'displayable' => $displayable,
            'calculator_version' => $computed['calculator_version'],
            'computed_at' => null,
            'event_count' => $computed['event_count'],
            'minimum_events' => $indexType === 'relationship_health' ? 5 : null,
            'inputs_digest' => $computed['inputs_digest'],
            'factors' => $computed['factors'],
        ];
    }
}
