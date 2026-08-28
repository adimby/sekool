<?php

namespace App\Domain\SchoolKit\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Models\PayerAccount;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\SchoolKit\Enums\KitFulfillment;
use App\Domain\SchoolKit\Enums\KitOrderStatus;
use App\Domain\SchoolKit\Models\KitDefinition;
use App\Domain\SchoolKit\Models\KitOrder;
use App\Domain\SchoolKit\Models\KitPack;

final class PlaceKitOrder
{
    /**
     * @param  array{
     *     enrollment_id: string,
     *     actor_person_id: string,
     *     fulfillment?: string,
     *     kit_pack_id?: string|null,
     *     kit_definition_id?: string|null
     * }  $data
     */
    public function execute(array $data): KitOrder
    {
        return TenantContext::runWithRlsBypass(function () use ($data): KitOrder {
            $fulfillment = KitFulfillment::tryFrom((string) ($data['fulfillment'] ?? KitFulfillment::Partner->value))
                ?? KitFulfillment::Partner;

            $enrollment = Enrollment::query()->withoutGlobalScopes()->find($data['enrollment_id']);
            if ($enrollment === null || $enrollment->status !== EnrollmentStatus::Active) {
                throw new DomainException('Inscription introuvable.', 404);
            }

            $pack = null;
            $definition = null;
            if ($fulfillment === KitFulfillment::Partner) {
                $packId = $data['kit_pack_id'] ?? null;
                $pack = is_string($packId) ? KitPack::query()->withoutGlobalScopes()->with('supplier')->find($packId) : null;
                if ($pack === null || (string) $pack->school_id !== (string) $enrollment->school_id) {
                    throw new DomainException('Pack introuvable.', 404);
                }
                $definition = KitDefinition::query()->withoutGlobalScopes()->find($pack->kit_definition_id);
            } else {
                $definitionId = $data['kit_definition_id'] ?? null;
                $definition = is_string($definitionId)
                    ? KitDefinition::query()->withoutGlobalScopes()->find($definitionId)
                    : null;
                if ($definition === null || (string) $definition->school_id !== (string) $enrollment->school_id) {
                    throw new DomainException('Liste de fournitures introuvable.', 404);
                }
            }

            $existing = KitOrder::query()
                ->withoutGlobalScopes()
                ->where('enrollment_id', $enrollment->id)
                ->where('kit_definition_id', $definition?->id)
                ->whereNotIn('status', [KitOrderStatus::Cancelled->value])
                ->first();
            if ($existing !== null) {
                throw new DomainException('Un choix existe déjà pour cet élève et cette liste.');
            }

            if ($fulfillment === KitFulfillment::Partner && $pack !== null) {
                $samePack = KitOrder::query()
                    ->withoutGlobalScopes()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('kit_pack_id', $pack->id)
                    ->whereNotIn('status', [KitOrderStatus::Cancelled->value])
                    ->first();
                if ($samePack !== null) {
                    throw new DomainException('Une commande existe déjà pour cet élève et ce pack.');
                }
            }

            $bps = (int) ($pack?->supplier?->commission_rate_bps ?? 0);
            $total = $fulfillment === KitFulfillment::Partner ? (int) $pack?->total_amount : 0;
            $commission = $fulfillment === KitFulfillment::Partner ? intdiv($total * $bps, 10_000) : 0;

            $payerId = PayerAccount::query()
                ->withoutGlobalScopes()
                ->where('school_id', $enrollment->school_id)
                ->value('id');

            $order = KitOrder::query()->create([
                'school_id' => $enrollment->school_id,
                'payer_account_id' => $payerId,
                'enrollment_id' => $enrollment->id,
                'kit_definition_id' => $definition?->id,
                'kit_pack_id' => $pack?->id,
                'supplier_id' => $pack?->supplier_id,
                'fulfillment' => $fulfillment,
                'status' => $fulfillment === KitFulfillment::Self ? KitOrderStatus::SelfSupplied : KitOrderStatus::Submitted,
                'total_amount' => $total,
                'commission_amount' => $commission,
                'placed_at' => now(),
                'placed_by_person_id' => $data['actor_person_id'],
            ]);

            Auditor::record('kit_order.placed', 'kit_order', $order->id, $enrollment->person_id, [
                'fulfillment' => $fulfillment->value,
                'pay_at_supplier' => $fulfillment === KitFulfillment::Partner,
            ]);

            return $order->load(['pack.supplier', 'enrollment.person', 'definition']);
        });
    }
}
