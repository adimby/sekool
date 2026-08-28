<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Collection\Actions\AssessEnrollmentRisk;
use App\Domain\Collection\Actions\ResolveSettledCollectionTasks;
use App\Domain\Finance\Enums\DocumentType;
use App\Domain\Finance\Enums\PaymentMethod;
use App\Domain\Finance\Models\Installment;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\PayerAccount;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Models\PaymentAllocation;
use App\Domain\Finance\Models\Receipt;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Reliability\Actions\ComputeFamilyReliability;
use App\Domain\Reliability\Actions\ComputeRelationshipHealth;
use App\Domain\Reliability\Actions\ComputeSchoolReliability;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\Reliability\Support\ReliabilityIndexes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class RecordPayment
{
    public function __construct(private readonly NextDocumentNumber $numbers) {}

    /**
     * @return array{payment: Payment, receipt: Receipt, created: bool}
     */
    public function execute(
        string $schoolId,
        string $invoiceId,
        int $amount,
        PaymentMethod $method,
        string $receivedOn,
        string $recordedByPersonId,
        ?string $idempotencyKey = null,
        ?string $reference = null,
        ?string $notes = null,
    ): array {
        if ($amount <= 0) {
            throw new DomainException('Le montant du paiement doit être strictement positif.');
        }

        if ($idempotencyKey !== null) {
            $replay = $this->existingByKey($schoolId, $idempotencyKey);
            if ($replay !== null) {
                return $replay;
            }
        }

        try {
            return DB::transaction(function () use (
                $schoolId,
                $invoiceId,
                $amount,
                $method,
                $receivedOn,
                $recordedByPersonId,
                $idempotencyKey,
                $reference,
                $notes,
            ): array {
                $invoice = Invoice::query()->lockForUpdate()->find($invoiceId);
                if ($invoice === null || (string) $invoice->school_id !== $schoolId) {
                    throw new DomainException('Facture introuvable.', 404);
                }

                $payer = PayerAccount::query()->lockForUpdate()->find($invoice->payer_account_id);
                if ($payer === null) {
                    throw new DomainException('Compte payeur introuvable.', 404);
                }

                $remaining = $invoice->remainingAmount();
                $applied = min($amount, $remaining);
                $credit = $amount - $applied;

                $payment = Payment::query()->create([
                    'school_id' => $schoolId,
                    'payer_account_id' => $payer->id,
                    'amount' => $amount,
                    'method' => $method,
                    'received_on' => $receivedOn,
                    'reference' => $reference,
                    'recorded_by_person_id' => $recordedByPersonId,
                    'idempotency_key' => $idempotencyKey,
                    'notes' => $notes,
                    'status' => 'posted',
                ]);

                $toAllocate = $applied;
                $late = false;
                $installments = Installment::query()
                    ->where('invoice_id', $invoice->id)
                    ->orderBy('due_on')
                    ->orderBy('sequence')
                    ->lockForUpdate()
                    ->get();

                foreach ($installments as $installment) {
                    if ($toAllocate <= 0) {
                        break;
                    }

                    $slot = $installment->remainingAmount();
                    if ($slot <= 0) {
                        continue;
                    }

                    $chunk = min($toAllocate, $slot);
                    PaymentAllocation::query()->create([
                        'school_id' => $schoolId,
                        'payment_id' => $payment->id,
                        'installment_id' => $installment->id,
                        'amount' => $chunk,
                    ]);
                    $installment->applyPayment($chunk);
                    $toAllocate -= $chunk;

                    if ($installment->due_on->lt($receivedOn)) {
                        $late = true;
                    }
                }

                if ($credit > 0) {
                    $payer->credit_balance_ariary += $credit;
                    $payer->save();
                }

                $invoice->refreshPaymentStatus();

                $receiptNumber = $this->numbers->allocate($schoolId, $invoice->school_year_id, DocumentType::Receipt);
                $receipt = Receipt::query()->create([
                    'school_id' => $schoolId,
                    'payment_id' => $payment->id,
                    'number' => $receiptNumber,
                    'issued_at' => now(),
                    'issued_by_person_id' => $recordedByPersonId,
                    'status' => 'issued',
                ]);

                $enrollment = $invoice->enrollment;
                TrustEvent::emit(
                    ReliabilityIndexes::SUBJECT_FAMILY,
                    $payer->family_id,
                    $late ? 'payment_late' : 'payment_on_time',
                    $schoolId,
                    'payment',
                    $payment->id,
                    [
                        'amount' => $amount,
                        'invoice_id' => $invoice->id,
                        'received_on' => $receivedOn,
                    ],
                );
                TrustEvent::emit(
                    ReliabilityIndexes::SUBJECT_SCHOOL,
                    $schoolId,
                    'payment_recorded',
                    $schoolId,
                    'payment',
                    (string) $payment->id,
                    [
                        'amount' => $amount,
                        'invoice_id' => $invoice->id,
                    ],
                );

                Auditor::record(
                    'payment.recorded',
                    'payment',
                    $payment->id,
                    $enrollment?->person_id,
                    [
                        'receipt_number' => $receiptNumber,
                        'amount' => $amount,
                        'method' => $method->value,
                    ],
                );

                if ($enrollment !== null) {
                    app(AssessEnrollmentRisk::class)->execute($schoolId, (string) $enrollment->id);
                    app(ResolveSettledCollectionTasks::class)->execute($schoolId, (string) $enrollment->id);
                }
                app(ComputeFamilyReliability::class)->execute($schoolId, (string) $payer->family_id);
                app(ComputeSchoolReliability::class)->execute($schoolId);
                app(ComputeRelationshipHealth::class)->execute($schoolId, (string) $payer->family_id);

                return [
                    'payment' => $payment->load('allocations'),
                    'receipt' => $receipt,
                    'created' => true,
                ];
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($idempotencyKey !== null) {
                $replay = $this->existingByKey($schoolId, $idempotencyKey);
                if ($replay !== null) {
                    return $replay;
                }
            }

            throw $e;
        }
    }

    /**
     * @return array{payment: Payment, receipt: Receipt, created: bool}|null
     */
    private function existingByKey(string $schoolId, string $idempotencyKey): ?array
    {
        $payment = Payment::query()
            ->where('school_id', $schoolId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($payment === null) {
            return null;
        }

        $receipt = Receipt::query()->where('payment_id', $payment->id)->firstOrFail();

        return [
            'payment' => $payment->load('allocations'),
            'receipt' => $receipt,
            'created' => false,
        ];
    }
}
