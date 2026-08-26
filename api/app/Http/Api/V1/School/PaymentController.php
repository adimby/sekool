<?php

namespace App\Http\Api\V1\School;

use App\Domain\Finance\Actions\RecordPayment;
use App\Domain\Finance\Enums\PaymentMethod;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Support\InvoicePayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentController extends Controller
{
    public function store(Request $request, RecordPayment $record): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string'],
            'received_on' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'uuid'],
            'reference' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $method = PaymentMethod::tryFrom($data['method']);
        if ($method === null) {
            return response()->json(['message' => 'Mode de paiement inconnu.'], 422);
        }

        $result = $record->execute(
            schoolId: (string) $request->route('school'),
            invoiceId: $data['invoice_id'],
            amount: (int) $data['amount'],
            method: $method,
            receivedOn: $data['received_on'] ?? now()->toDateString(),
            recordedByPersonId: $request->user()->person_id,
            idempotencyKey: $data['idempotency_key'] ?? null,
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
        );

        $invoice = Invoice::query()->find($data['invoice_id']);

        return response()->json([
            'data' => InvoicePayload::payment($result['payment'], $result['receipt']),
            'invoice' => $invoice === null ? null : InvoicePayload::make($invoice),
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function export(string $school): StreamedResponse
    {
        $filename = 'paiements-'.$school.'.csv';

        return response()->streamDownload(function () use ($school): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['recu', 'date', 'montant', 'mode', 'eleve', 'identifiant', 'facture'], ';');

            $payments = Payment::query()
                ->with(['receipt', 'payerAccount'])
                ->orderBy('received_on')
                ->orderBy('created_at')
                ->get();

            foreach ($payments as $payment) {
                $invoice = Invoice::query()
                    ->where('payer_account_id', $payment->payer_account_id)
                    ->with('enrollment.person')
                    ->latest('issued_on')
                    ->first();
                $person = $invoice?->enrollment?->person;

                fputcsv($out, [
                    $payment->receipt?->number,
                    $payment->received_on?->toDateString(),
                    $payment->amount,
                    $payment->method->value,
                    $person === null ? '' : $person->first_name.' '.$person->last_name,
                    $person?->public_id ?? '',
                    $invoice?->number ?? '',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
