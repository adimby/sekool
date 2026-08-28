<?php

namespace App\Domain\Finance\Support;

use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeItem;
use App\Domain\Finance\Models\FeeSchedule;

final class FeeSchedulePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function make(FeeSchedule $schedule): array
    {
        $schedule->loadMissing(['items', 'gradeLevel', 'schoolYear']);

        $status = $schedule->status instanceof FeeScheduleStatus
            ? $schedule->status->value
            : (string) $schedule->status;

        return [
            'id' => $schedule->id,
            'school_id' => $schedule->school_id,
            'school_year_id' => $schedule->school_year_id,
            'school_year' => $schedule->schoolYear === null ? null : [
                'id' => $schedule->schoolYear->id,
                'label' => $schedule->schoolYear->label,
                'starts_on' => $schedule->schoolYear->starts_on?->toDateString(),
                'ends_on' => $schedule->schoolYear->ends_on?->toDateString(),
                'is_current' => $schedule->schoolYear->is_current,
            ],
            'grade_level_id' => $schedule->grade_level_id,
            'grade_level' => $schedule->gradeLevel === null ? null : [
                'id' => $schedule->gradeLevel->id,
                'name' => $schedule->gradeLevel->name,
            ],
            'name' => $schedule->name,
            'status' => $status,
            'locked' => $schedule->isLocked(),
            'copied_from_schedule_id' => $schedule->copied_from_schedule_id,
            'adjustment_type' => $schedule->adjustment_type?->value ?? $schedule->adjustment_type,
            'adjustment_amount' => $schedule->adjustment_amount,
            'adjustment_percent_bps' => $schedule->adjustment_percent_bps,
            'submitted_at' => $schedule->submitted_at?->toIso8601String(),
            'locked_at' => $schedule->locked_at?->toIso8601String(),
            'unlock_requested_at' => $schedule->unlock_requested_at?->toIso8601String(),
            'unlock_request_reason' => $schedule->unlock_request_reason,
            'total_amount' => $schedule->items->sum(fn (FeeItem $item): int => (int) $item->amount),
            'items' => $schedule->items
                ->sortBy([
                    ['due_on', 'asc'],
                    ['code', 'asc'],
                ])
                ->values()
                ->map(fn (FeeItem $item): array => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'label' => $item->label,
                    'amount' => $item->amount,
                    'due_on' => $item->due_on?->toDateString(),
                    'category' => $item->category instanceof FeeCategory
                        ? $item->category->value
                        : (string) $item->category,
                    'is_recurring' => $item->is_recurring,
                ]),
        ];
    }
}
