<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Finance\Enums\DocumentType;
use App\Domain\Finance\Enums\InstallmentStatus;
use App\Domain\Finance\Enums\InvoiceStatus;
use App\Domain\Finance\Models\FeeItem;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Finance\Models\Installment;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\InvoiceLine;
use App\Domain\Finance\Models\PayerAccount;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Support\Money;
use Illuminate\Support\Facades\DB;

final class GenerateInvoice
{
    public function __construct(private readonly NextDocumentNumber $numbers) {}

    /**
     * @return array{invoice: Invoice, created: bool}
     */
    public function execute(
        string $schoolId,
        string $enrollmentId,
        string $actorPersonId,
        int $discountAmount = 0,
        ?string $discountReason = null,
    ): array {
        if ($discountAmount < 0) {
            throw new DomainException('Une remise ne peut pas être négative.');
        }

        if ($discountAmount > 0 && ($discountReason === null || trim($discountReason) === '')) {
            throw new DomainException('Une remise exige un motif.');
        }

        return DB::transaction(function () use (
            $schoolId,
            $enrollmentId,
            $actorPersonId,
            $discountAmount,
            $discountReason,
        ): array {
            $enrollment = Enrollment::query()->lockForUpdate()->find($enrollmentId);
            if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
                throw new DomainException('Inscription introuvable.', 404);
            }
            $enrollment->load('classroom');

            if ($enrollment->status !== EnrollmentStatus::Active) {
                throw new DomainException('Seule une inscription active peut être facturée.');
            }

            $existing = Invoice::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('school_year_id', $enrollment->school_year_id)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return ['invoice' => $existing->load(['lines', 'installments']), 'created' => false];
            }

            $items = $this->feeItemsFor($enrollment);
            if ($items->isEmpty()) {
                throw new DomainException('Aucun barème de frais n\'est défini pour cette classe / année.');
            }

            $total = Money::zero();
            foreach ($items as $item) {
                $total = $total->plus(Money::of($item->amount));
            }

            if ($discountAmount > $total->amount) {
                throw new DomainException('La remise dépasse le montant de la facture.');
            }

            $payer = $this->payerAccountFor($schoolId, $enrollment->person_id);
            $number = $this->numbers->allocate($schoolId, $enrollment->school_year_id, DocumentType::Invoice);
            $net = $total->minus(Money::of($discountAmount));

            $invoice = Invoice::query()->create([
                'school_id' => $schoolId,
                'enrollment_id' => $enrollment->id,
                'payer_account_id' => $payer->id,
                'school_year_id' => $enrollment->school_year_id,
                'number' => $number,
                'issued_on' => now()->toDateString(),
                'total_amount' => $total->amount,
                'discount_amount' => $discountAmount,
                'discount_reason' => $discountAmount > 0 ? $discountReason : null,
                'net_amount' => $net->amount,
                'status' => InvoiceStatus::Issued,
            ]);

            $remainingDiscount = $discountAmount;
            $itemDiscounts = [];
            foreach ($items->reverse() as $item) {
                $take = min($remainingDiscount, $item->amount);
                $itemDiscounts[$item->id] = $take;
                $remainingDiscount -= $take;
            }

            foreach ($items->values() as $index => $item) {
                $lineDiscount = $itemDiscounts[$item->id] ?? 0;
                $lineReason = $lineDiscount > 0 ? $discountReason : null;

                InvoiceLine::query()->create([
                    'school_id' => $schoolId,
                    'invoice_id' => $invoice->id,
                    'fee_item_id' => $item->id,
                    'label' => $item->label,
                    'amount' => $item->amount,
                    'discount_amount' => $lineDiscount,
                    'discount_reason' => $lineReason,
                    'sequence' => $index + 1,
                ]);

                $installmentAmount = $item->amount - $lineDiscount;
                if ($installmentAmount > 0) {
                    Installment::query()->create([
                        'school_id' => $schoolId,
                        'invoice_id' => $invoice->id,
                        'sequence' => $index + 1,
                        'due_on' => $item->due_on->toDateString(),
                        'amount' => $installmentAmount,
                        'paid_amount' => 0,
                        'status' => InstallmentStatus::Pending,
                    ]);
                }
            }

            Auditor::record(
                'invoice.issued',
                'invoice',
                $invoice->id,
                $enrollment->person_id,
                ['number' => $number, 'net_amount' => $net->amount, 'actor_person_id' => $actorPersonId],
            );

            return ['invoice' => $invoice->load(['lines', 'installments']), 'created' => true];
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, FeeItem>
     */
    private function feeItemsFor(Enrollment $enrollment)
    {
        $gradeId = $enrollment->classroom?->grade_level_id;

        $schedule = null;
        if ($gradeId !== null) {
            $schedule = FeeSchedule::query()
                ->where('school_year_id', $enrollment->school_year_id)
                ->where('grade_level_id', $gradeId)
                ->where('status', 'active')
                ->first();
        }

        $schedule ??= FeeSchedule::query()
            ->where('school_year_id', $enrollment->school_year_id)
            ->whereNull('grade_level_id')
            ->where('status', 'active')
            ->first();

        if ($schedule === null) {
            return collect();
        }

        return FeeItem::query()
            ->where('fee_schedule_id', $schedule->id)
            ->orderBy('due_on')
            ->orderBy('code')
            ->get();
    }

    private function payerAccountFor(string $schoolId, string $studentPersonId): PayerAccount
    {
        $membership = FamilyMember::query()
            ->where('person_id', $studentPersonId)
            ->whereNull('left_at')
            ->first();

        if ($membership === null) {
            throw new DomainException('Aucun foyer n\'est rattaché à cet élève.');
        }

        $adult = FamilyMember::query()
            ->where('family_id', $membership->family_id)
            ->where('role_in_family', 'adult')
            ->whereNull('left_at')
            ->orderBy('joined_at')
            ->first();

        if ($adult === null) {
            throw new DomainException('Aucun responsable financier n\'est rattaché au foyer.');
        }

        return PayerAccount::query()->firstOrCreate(
            [
                'school_id' => $schoolId,
                'family_id' => $membership->family_id,
                'responsible_person_id' => $adult->person_id,
            ],
            [
                'credit_balance_ariary' => 0,
                'status' => 'active',
            ],
        );
    }
}
