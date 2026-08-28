<?php

namespace App\Domain\Collection\Support;

use App\Domain\Finance\Models\Invoice;

final class EnrollmentInstallments
{
    /**
     * @return list<array{due_on: string, amount: int, paid_amount: int, last_paid_on: ?string, remaining: int, invoice_id: string, installment_id: string}>
     */
    public static function snapshot(string $enrollmentId): array
    {
        $invoices = Invoice::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('status', '!=', 'cancelled')
            ->with(['installments.allocations.payment'])
            ->get();

        $rows = [];
        foreach ($invoices as $invoice) {
            foreach ($invoice->installments as $installment) {
                $lastPaid = null;
                foreach ($installment->allocations as $allocation) {
                    $on = $allocation->payment?->received_on?->toDateString();
                    if ($on !== null && ($lastPaid === null || $on > $lastPaid)) {
                        $lastPaid = $on;
                    }
                }

                $rows[] = [
                    'due_on' => $installment->due_on->toDateString(),
                    'amount' => (int) $installment->amount,
                    'paid_amount' => (int) $installment->paid_amount,
                    'last_paid_on' => $lastPaid,
                    'remaining' => $installment->remainingAmount(),
                    'invoice_id' => (string) $invoice->id,
                    'installment_id' => (string) $installment->id,
                ];
            }
        }

        return $rows;
    }

    public static function remaining(string $enrollmentId): int
    {
        $total = 0;
        foreach (self::snapshot($enrollmentId) as $row) {
            $total += $row['remaining'];
        }

        return $total;
    }
}
