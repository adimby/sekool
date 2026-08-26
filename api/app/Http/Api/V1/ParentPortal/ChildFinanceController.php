<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Models\PaymentAllocation;
use App\Domain\Finance\Support\InvoicePayload;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChildFinanceController extends Controller
{
    public function __invoke(Request $request, string $person): JsonResponse
    {
        if (! ParentAuthorization::isLegalGuardianOf($request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return TenantContext::runWithRlsBypass(function () use ($person): JsonResponse {
            $enrollments = Enrollment::query()
                ->withoutGlobalScopes()
                ->with(['school', 'classroom', 'schoolYear'])
                ->where('person_id', $person)
                ->where('status', 'active')
                ->get();

            $invoices = Invoice::query()
                ->withoutGlobalScopes()
                ->with('installments')
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->where('status', '!=', 'cancelled')
                ->get()
                ->keyBy('enrollment_id');

            $installmentToInvoice = [];
            foreach ($invoices as $invoice) {
                foreach ($invoice->installments as $installment) {
                    $installmentToInvoice[$installment->id] = $invoice->id;
                }
            }

            $allocations = PaymentAllocation::query()
                ->withoutGlobalScopes()
                ->whereIn('installment_id', array_keys($installmentToInvoice))
                ->get();

            $payments = Payment::query()
                ->withoutGlobalScopes()
                ->with('receipt')
                ->whereIn('id', $allocations->pluck('payment_id'))
                ->orderByDesc('received_on')
                ->get()
                ->keyBy('id');

            $paymentsByInvoice = [];
            foreach ($allocations as $allocation) {
                $invoiceId = $installmentToInvoice[$allocation->installment_id] ?? null;
                $payment = $payments->get($allocation->payment_id);
                if ($invoiceId === null || $payment === null) {
                    continue;
                }
                $paymentsByInvoice[$invoiceId][$payment->id] = $payment;
            }

            $data = $enrollments->map(function (Enrollment $enrollment) use ($invoices, $paymentsByInvoice) {
                $invoice = $invoices->get($enrollment->id);
                $relatedPayments = collect($paymentsByInvoice[$invoice?->id] ?? []);

                return [
                    'enrollment_id' => $enrollment->id,
                    'school' => $enrollment->school === null ? null : [
                        'id' => $enrollment->school->id,
                        'name' => $enrollment->school->name,
                    ],
                    'classroom' => $enrollment->classroom === null ? null : [
                        'id' => $enrollment->classroom->id,
                        'name' => $enrollment->classroom->name,
                    ],
                    'invoice' => $invoice === null ? null : InvoicePayload::make($invoice),
                    'payments' => $relatedPayments->values()->map(fn (Payment $payment): array => [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'method' => $payment->method->value,
                        'received_on' => $payment->received_on?->toDateString(),
                        'receipt_number' => $payment->receipt?->number,
                    ]),
                ];
            })->values();

            $remaining = $invoices->sum(fn (Invoice $invoice): int => $invoice->remainingAmount());

            return response()->json([
                'remaining_amount' => $remaining,
                'data' => $data,
            ]);
        });
    }
}
