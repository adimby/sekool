<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FeeAdjustmentType;
use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Finance\Models\FeeItem;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Finance\Support\FeeAmountAdjuster;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Support\Facades\DB;

final class CopyFeeSchedulesFromYear
{
    public function __construct(private readonly CreateFeeSchedule $create) {}

    /**
     * @return list<FeeSchedule>
     */
    public function execute(
        string $schoolId,
        string $sourceYearId,
        string $targetYearId,
        ?FeeAdjustmentType $adjustmentType = null,
        int $adjustmentAmount = 0,
        int $adjustmentPercentBps = 0,
    ): array {
        if ($sourceYearId === $targetYearId) {
            throw new DomainException('Choisissez l’année précédente, pas la même année.');
        }

        $sourceYear = SchoolYear::query()->find($sourceYearId);
        $targetYear = SchoolYear::query()->find($targetYearId);

        if ($sourceYear === null || (string) $sourceYear->school_id !== $schoolId) {
            throw new DomainException('Année source introuvable.', 404);
        }
        if ($targetYear === null || (string) $targetYear->school_id !== $schoolId) {
            throw new DomainException('Année cible introuvable.', 404);
        }

        $sources = FeeSchedule::query()
            ->with('items')
            ->where('school_year_id', $sourceYearId)
            ->orderByRaw('grade_level_id IS NULL')
            ->orderBy('name')
            ->get();

        if ($sources->isEmpty()) {
            throw new DomainException('Aucun barème à copier pour l’année source.');
        }

        $yearShift = $targetYear->starts_on->year - $sourceYear->starts_on->year;

        return DB::transaction(function () use (
            $schoolId,
            $sources,
            $targetYear,
            $yearShift,
            $adjustmentType,
            $adjustmentAmount,
            $adjustmentPercentBps,
        ): array {
            $created = [];

            foreach ($sources as $source) {
                $items = $source->items
                    ->sortBy([
                        ['due_on', 'asc'],
                        ['code', 'asc'],
                    ])
                    ->values()
                    ->map(function (FeeItem $item) use ($yearShift, $adjustmentType, $adjustmentAmount, $adjustmentPercentBps): array {
                        $amount = (int) $item->amount;
                        if ($adjustmentType !== null) {
                            $amount = FeeAmountAdjuster::apply(
                                $amount,
                                $adjustmentType,
                                $adjustmentAmount,
                                $adjustmentPercentBps,
                            );
                        }

                        $category = $item->category instanceof FeeCategory
                            ? $item->category->value
                            : (string) $item->category;

                        return [
                            'code' => $item->code,
                            'label' => $item->label,
                            'amount' => $amount,
                            'due_on' => $item->due_on->copy()->addYears($yearShift)->toDateString(),
                            'category' => $category,
                            'is_recurring' => $item->is_recurring,
                        ];
                    })
                    ->all();

                $schedule = $this->create->execute(
                    schoolId: $schoolId,
                    schoolYearId: $targetYear->id,
                    gradeLevelId: $source->grade_level_id,
                    name: $this->nameFor($source->name, $targetYear->label),
                    items: $items,
                    copiedFromScheduleId: $source->id,
                );

                if ($adjustmentType !== null) {
                    $schedule->forceFill([
                        'adjustment_type' => $adjustmentType,
                        'adjustment_amount' => $adjustmentType === FeeAdjustmentType::Amount ? $adjustmentAmount : null,
                        'adjustment_percent_bps' => $adjustmentType === FeeAdjustmentType::Percent ? $adjustmentPercentBps : null,
                    ])->save();
                }

                $created[] = $schedule->fresh(['items', 'gradeLevel', 'schoolYear']);
            }

            Auditor::record('fee_schedule.copied_year', 'school_year', $targetYear->id, null, [
                'source_year_id' => $sources->first()?->school_year_id,
                'count' => count($created),
                'adjustment_type' => $adjustmentType?->value,
            ]);

            return $created;
        });
    }

    private function nameFor(string $sourceName, string $targetLabel): string
    {
        if (preg_match('/\d{4}\s*[-–]\s*\d{4}/', $sourceName) === 1) {
            return (string) preg_replace('/\d{4}\s*[-–]\s*\d{4}/', $targetLabel, $sourceName, 1);
        }

        return $sourceName.' · '.$targetLabel;
    }
}
