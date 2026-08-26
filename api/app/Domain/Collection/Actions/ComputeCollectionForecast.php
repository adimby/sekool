<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Models\CollectionForecast;
use App\Domain\Collection\Models\RiskAssessment;
use App\Domain\Collection\Support\CollectionForecastCalculator;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Models\Installment;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ComputeCollectionForecast
{
    public function __construct(private readonly CollectionForecastCalculator $calculator) {}

    public function execute(string $schoolId, ?DateTimeInterface $asOf = null): CollectionForecast
    {
        $asOf = CarbonImmutable::parse($asOf ?? 'now');
        $weekStart = $asOf->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
        $weekEnd = $asOf->endOfWeek(CarbonImmutable::SUNDAY)->toDateString();
        $asOfDate = $asOf->toDateString();

        $due = Installment::query()
            ->with('invoice')
            ->whereBetween('due_on', [$weekStart, $weekEnd])
            ->get()
            ->filter(fn (Installment $row): bool => $row->remainingAmount() > 0);

        $schoolRatio = $this->schoolOnTimeRatio($asOfDate);
        $ratios = $this->familyRatios();

        $rows = [];
        foreach ($due as $installment) {
            $enrollmentId = $installment->invoice?->enrollment_id;
            $rows[] = [
                'remaining' => $installment->remainingAmount(),
                'family_on_time_ratio' => $enrollmentId === null ? null : ($ratios[$enrollmentId] ?? null),
            ];
        }

        $forecasted = $this->calculator->forecast($rows, $schoolRatio);

        return CollectionForecast::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'week_starting_on' => $weekStart,
            ],
            [
                'expected_amount' => $forecasted['expected_amount'],
                'confidence_low_amount' => $forecasted['confidence_low_amount'],
                'confidence_high_amount' => $forecasted['confidence_high_amount'],
                'method_version' => $forecasted['method_version'],
                'computed_at' => now(),
                'breakdown' => [
                    'k' => $forecasted['k'],
                    'school_on_time_ratio' => $forecasted['school_on_time_ratio'],
                    'due_count' => count($rows),
                    'due_remaining' => array_sum(array_column($rows, 'remaining')),
                ],
            ],
        );
    }

    private function schoolOnTimeRatio(string $asOfDate): float
    {
        $installments = Installment::query()->with('allocations.payment')->get();
        $considered = 0;
        $onTime = 0;

        foreach ($installments as $installment) {
            $dueOn = $installment->due_on->toDateString();
            $paid = (int) $installment->paid_amount >= (int) $installment->amount;
            if ($dueOn > $asOfDate && ! $paid) {
                continue;
            }
            $considered++;
            $lastPaid = null;
            foreach ($installment->allocations as $allocation) {
                $on = $allocation->payment?->received_on?->toDateString();
                if ($on !== null && ($lastPaid === null || $on > $lastPaid)) {
                    $lastPaid = $on;
                }
            }
            if ($paid) {
                if (($lastPaid ?? $dueOn) <= $dueOn) {
                    $onTime++;
                }
            } elseif ($dueOn >= $asOfDate) {
                $onTime++;
            }
        }

        return $considered === 0 ? 0.7 : $onTime / $considered;
    }

    /**
     * @return array<string, float>
     */
    private function familyRatios(): array
    {
        $ratios = [];
        $enrollmentIds = Enrollment::query()
            ->where('status', EnrollmentStatus::Active)
            ->pluck('id');

        foreach (RiskAssessment::query()->whereIn('enrollment_id', $enrollmentIds)->get() as $assessment) {
            if ($assessment->on_time_ratio !== null) {
                $ratios[(string) $assessment->enrollment_id] = (float) $assessment->on_time_ratio;
            }
        }

        return $ratios;
    }
}
