<?php

namespace App\Domain\Finance\Support;

use App\Domain\Finance\Models\Installment;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\InvoiceLine;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Models\Receipt;

final class InvoicePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines', 'installments', 'enrollment.person']);

        $paid = $invoice->paidAmount();

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'enrollment_id' => $invoice->enrollment_id,
            'payer_account_id' => $invoice->payer_account_id,
            'school_year_id' => $invoice->school_year_id,
            'issued_on' => $invoice->issued_on?->toDateString(),
            'total_amount' => $invoice->total_amount,
            'discount_amount' => $invoice->discount_amount,
            'discount_reason' => $invoice->discount_reason,
            'net_amount' => $invoice->net_amount,
            'paid_amount' => $paid,
            'remaining_amount' => $invoice->net_amount - $paid,
            'status' => $invoice->status->value,
            'student' => $invoice->enrollment?->person === null ? null : [
                'id' => $invoice->enrollment->person->id,
                'public_id' => $invoice->enrollment->person->public_id,
                'first_name' => $invoice->enrollment->person->first_name,
                'last_name' => $invoice->enrollment->person->last_name,
            ],
            'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => [
                'id' => $line->id,
                'label' => $line->label,
                'amount' => $line->amount,
                'discount_amount' => $line->discount_amount,
                'discount_reason' => $line->discount_reason,
                'sequence' => $line->sequence,
            ])->values(),
            'installments' => $invoice->installments->map(fn (Installment $row): array => [
                'id' => $row->id,
                'sequence' => $row->sequence,
                'due_on' => $row->due_on?->toDateString(),
                'amount' => $row->amount,
                'paid_amount' => $row->paid_amount,
                'remaining_amount' => $row->remainingAmount(),
                'status' => $row->status->value,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payment(Payment $payment, Receipt $receipt): array
    {
        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'method' => $payment->method->value,
            'received_on' => $payment->received_on?->toDateString(),
            'reference' => $payment->reference,
            'status' => $payment->status,
            'receipt' => [
                'id' => $receipt->id,
                'number' => $receipt->number,
                'issued_at' => $receipt->issued_at?->toIso8601String(),
            ],
        ];
    }
}
