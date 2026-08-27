<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FeeAdjustmentType;
use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeItem;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Finance\Support\FeeAmountAdjuster;
use App\Domain\Finance\Support\SyncFeeItems;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class AdjustFeeSchedule
{
    public function __construct(private readonly SyncFeeItems $syncItems) {}

    public function execute(
        string $schoolId,
        string $scheduleId,
        FeeAdjustmentType $type,
        int $amountDelta = 0,
        int $percentBps = 0,
    ): FeeSchedule {
        return DB::transaction(function () use ($schoolId, $scheduleId, $type, $amountDelta, $percentBps): FeeSchedule {
            $schedule = FeeSchedule::query()->with('items')->lockForUpdate()->find($scheduleId);
            if ($schedule === null || (string) $schedule->school_id !== $schoolId) {
                throw new DomainException('Barème introuvable.', 404);
            }

            if ($schedule->isLocked()) {
                throw new DomainException('Ce barème est verrouillé. Toute modification exige une demande de support FANABE.');
            }

            $items = $schedule->items
                ->sortBy([
                    ['due_on', 'asc'],
                    ['code', 'asc'],
                ])
                ->values()
                ->map(function (FeeItem $item) use ($type, $amountDelta, $percentBps): array {
                    $category = $item->category instanceof FeeCategory
                        ? $item->category->value
                        : (string) $item->category;

                    return [
                        'code' => $item->code,
                        'label' => $item->label,
                        'amount' => FeeAmountAdjuster::apply((int) $item->amount, $type, $amountDelta, $percentBps),
                        'due_on' => $item->due_on->toDateString(),
                        'category' => $category,
                        'is_recurring' => $item->is_recurring,
                    ];
                })
                ->all();

            $wasPending = $schedule->status === FeeScheduleStatus::PendingValidation;

            $schedule->forceFill([
                'adjustment_type' => $type,
                'adjustment_amount' => $type === FeeAdjustmentType::Amount ? $amountDelta : null,
                'adjustment_percent_bps' => $type === FeeAdjustmentType::Percent ? $percentBps : null,
                'status' => $wasPending ? FeeScheduleStatus::Draft : $schedule->status,
                'submitted_at' => $wasPending ? null : $schedule->submitted_at,
                'submitted_by_person_id' => $wasPending ? null : $schedule->submitted_by_person_id,
            ])->save();

            $this->syncItems->execute($schedule, $items);

            Auditor::record('fee_schedule.adjusted', 'fee_schedule', $schedule->id, null, [
                'type' => $type->value,
                'amount' => $amountDelta,
                'percent_bps' => $percentBps,
            ]);

            return $schedule->load(['items', 'gradeLevel', 'schoolYear']);
        });
    }
}
