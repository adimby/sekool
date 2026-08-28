<?php

namespace App\Domain\Collection\Support;

use App\Domain\Collection\Enums\RiskLevel;
use DateTimeInterface;

/**
 * Deterministic collection-risk.v1 (Q-08). First matching row wins.
 * Does not import authorization code.
 */
final class RiskCalculator
{
    public const VERSION = 'collection-risk.v1';

    /**
     * @param  list<array{due_on: string, amount: int, paid_amount: int, last_paid_on?: ?string}>  $installments
     * @return array{
     *     level: RiskLevel,
     *     outstanding_amount: int,
     *     days_overdue: int,
     *     on_time_ratio: float,
     *     late_last_3: int,
     *     late_last_4: int,
     *     calculator_version: string,
     *     factors: list<array{factor_key: string, human_label: string, contribution: int, evidence: array<string, mixed>}>
     * }
     */
    public function assess(array $installments, DateTimeInterface $asOf): array
    {
        $asOfDate = $asOf->format('Y-m-d');
        $outstanding = 0;
        $daysOverdue = 0;
        $dueOrPaid = [];

        foreach ($installments as $row) {
            $remaining = max(0, $row['amount'] - $row['paid_amount']);
            $outstanding += $remaining;
            $dueOn = $row['due_on'];
            if ($remaining > 0 && $dueOn < $asOfDate) {
                $days = (int) ((strtotime($asOfDate) - strtotime($dueOn)) / 86400);
                $daysOverdue = max($daysOverdue, $days);
            }

            if ($dueOn <= $asOfDate || $row['paid_amount'] >= $row['amount']) {
                $dueOrPaid[] = $row;
            }
        }

        usort($dueOrPaid, fn (array $a, array $b): int => strcmp($b['due_on'], $a['due_on']));

        $onTime = 0;
        foreach ($dueOrPaid as $row) {
            if ($this->isOnTime($row, $asOfDate)) {
                $onTime++;
            }
        }

        $considered = count($dueOrPaid);
        $onTimeRatio = $considered === 0 ? 1.0 : $onTime / $considered;
        $lateLast3 = $this->lateCount(array_slice($dueOrPaid, 0, 3), $asOfDate);
        $lateLast4 = $this->lateCount(array_slice($dueOrPaid, 0, 4), $asOfDate);

        $level = $this->level($daysOverdue, $onTimeRatio, $lateLast3, $lateLast4);
        $factors = $this->factors($level, $daysOverdue, $onTimeRatio, $lateLast3, $lateLast4, $outstanding);

        return [
            'level' => $level,
            'outstanding_amount' => $outstanding,
            'days_overdue' => $daysOverdue,
            'on_time_ratio' => round($onTimeRatio, 4),
            'late_last_3' => $lateLast3,
            'late_last_4' => $lateLast4,
            'calculator_version' => self::VERSION,
            'factors' => $factors,
        ];
    }

    /**
     * @param  array{due_on: string, amount: int, paid_amount: int, last_paid_on?: ?string}  $row
     */
    private function isOnTime(array $row, string $asOfDate): bool
    {
        if ($row['paid_amount'] < $row['amount']) {
            return $row['due_on'] >= $asOfDate;
        }

        $paidOn = $row['last_paid_on'] ?? $row['due_on'];

        return $paidOn <= $row['due_on'];
    }

    /**
     * @param  list<array{due_on: string, amount: int, paid_amount: int, last_paid_on?: ?string}>  $rows
     */
    private function lateCount(array $rows, string $asOfDate): int
    {
        $late = 0;
        foreach ($rows as $row) {
            if (! $this->isOnTime($row, $asOfDate) && $row['due_on'] < $asOfDate) {
                $late++;
            }
        }

        return $late;
    }

    private function level(int $daysOverdue, float $onTimeRatio, int $lateLast3, int $lateLast4): RiskLevel
    {
        if ($daysOverdue > 60 || ($daysOverdue > 30 && $onTimeRatio < 0.5)) {
            return RiskLevel::Critical;
        }

        if (($daysOverdue >= 31 && $daysOverdue <= 60) || ($daysOverdue > 15 && $lateLast4 >= 2)) {
            return RiskLevel::High;
        }

        if (($daysOverdue >= 8 && $daysOverdue <= 30) || $lateLast3 >= 1) {
            return RiskLevel::Medium;
        }

        return RiskLevel::Low;
    }

    /**
     * @return list<array{factor_key: string, human_label: string, contribution: int, evidence: array<string, mixed>}>
     */
    private function factors(RiskLevel $level, int $daysOverdue, float $onTimeRatio, int $lateLast3, int $lateLast4, int $outstanding): array
    {
        $factors = [
            [
                'factor_key' => 'days_overdue',
                'human_label' => 'Ancienneté de la créance',
                'contribution' => min(100, $daysOverdue),
                'evidence' => ['days_overdue' => $daysOverdue, 'outstanding_amount' => $outstanding],
            ],
            [
                'factor_key' => 'on_time_ratio',
                'human_label' => 'Taux de ponctualité',
                'contribution' => (int) round((1 - $onTimeRatio) * 100),
                'evidence' => ['on_time_ratio' => $onTimeRatio],
            ],
        ];

        if ($lateLast3 > 0 || $lateLast4 > 0) {
            $factors[] = [
                'factor_key' => 'recent_lates',
                'human_label' => 'Retards récents sur les échéances',
                'contribution' => $lateLast4 * 20,
                'evidence' => ['late_last_3' => $lateLast3, 'late_last_4' => $lateLast4],
            ];
        }

        $factors[] = [
            'factor_key' => 'rule_matched',
            'human_label' => 'Règle Q-08 appliquée : '.$level->label(),
            'contribution' => $level->rank() * 25,
            'evidence' => ['calculator_version' => self::VERSION, 'level' => $level->value],
        ];

        return $factors;
    }
}
