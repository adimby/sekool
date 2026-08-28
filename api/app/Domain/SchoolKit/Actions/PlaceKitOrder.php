<?php

namespace App\Domain\SchoolKit\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Models\PayerAccount;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\SchoolKit\Enums\KitOrderStatus;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\SchoolKit\Models\KitOrder;
use App\Domain\SchoolKit\Models\KitPack;

final class PlaceKitOrder
{
    public function execute(string $enrollmentId, string $packId, string $actorPersonId): KitOrder
    {
        $enrollment = Enrollment::query()->withoutGlobalScopes()->find($enrollmentId);
        if ($enrollment === null || $enrollment->status !== EnrollmentStatus::Active) {
            throw new DomainException('Inscription introuvable.', 404);
        }

        $pack = KitPack::query()->withoutGlobalScopes()->with('supplier')->find($packId);
        if ($pack === null || (string) $pack->school_id !== (string) $enrollment->school_id) {
            throw new DomainException('Pack introuvable.', 404);
        }

        $existing = KitOrder::query()
            ->withoutGlobalScopes()
            ->where('enrollment_id', $enrollment->id)
            ->where('kit_pack_id', $pack->id)
            ->where('status', '!=', KitOrderStatus::Cancelled->value)
            ->first();
        if ($existing !== null) {
            throw new DomainException('Une commande existe déjà pour cet élève et ce pack.');
        }

        $bps = (int) ($pack->supplier?->commission_rate_bps ?? 0);
        $commission = intdiv($pack->total_amount * $bps, 10_000);

        $payerId = PayerAccount::query()
            ->withoutGlobalScopes()
            ->where('school_id', $enrollment->school_id)
            ->value('id');

        $order = TenantContext::runWithRlsBypass(fn () => KitOrder::query()->create([
            'school_id' => $enrollment->school_id,
            'payer_account_id' => $payerId,
            'enrollment_id' => $enrollment->id,
            'kit_pack_id' => $pack->id,
            'supplier_id' => $pack->supplier_id,
            'status' => KitOrderStatus::Submitted,
            'total_amount' => $pack->total_amount,
            'commission_amount' => $commission,
            'placed_at' => now(),
            'placed_by_person_id' => $actorPersonId,
        ]));

        Auditor::record('kit_order.placed', 'kit_order', $order->id, $enrollment->person_id, [
            'pay_at_supplier' => true,
        ]);

        return TenantContext::runWithRlsBypass(
            fn () => $order->load(['pack.supplier', 'enrollment.person']),
        );
    }
}
