<?php

namespace App\Domain\SchoolKit\Support;

use App\Domain\SchoolKit\Enums\KitOrderStatus;
use App\Domain\SchoolKit\Enums\KitPackTier;
use App\Domain\SchoolKit\Models\KitDefinition;
use App\Domain\SchoolKit\Models\KitOrder;
use App\Domain\SchoolKit\Models\KitPack;

final class KitPayload
{
    /** @return array<string, mixed> */
    public static function definition(KitDefinition $definition): array
    {
        $definition->loadMissing(['needs', 'packs.supplier', 'gradeLevel']);

        return [
            'id' => $definition->id,
            'name' => $definition->name,
            'status' => $definition->status,
            'school_year_id' => $definition->school_year_id,
            'grade_level_id' => $definition->grade_level_id,
            'grade_level' => $definition->gradeLevel?->name,
            'needs' => $definition->needs->map(fn ($need): array => [
                'id' => $need->id,
                'label' => $need->label,
                'quantity' => $need->quantity,
            ])->values(),
            'packs' => $definition->packs->map(fn (KitPack $pack): array => self::pack($pack))->values(),
        ];
    }

    /** @return array<string, mixed> */
    public static function pack(KitPack $pack): array
    {
        $pack->loadMissing('supplier');
        $tier = $pack->tier instanceof KitPackTier ? $pack->tier : KitPackTier::tryFrom((string) $pack->tier);

        return [
            'id' => $pack->id,
            'tier' => $tier?->value ?? (string) $pack->tier,
            'tier_label' => $tier?->label() ?? (string) $pack->tier,
            'total_amount' => (int) $pack->total_amount,
            'supplier' => $pack->supplier === null ? null : [
                'id' => $pack->supplier->id,
                'name' => $pack->supplier->name,
                'contact' => $pack->supplier->contact,
            ],
            'pay_instruction' => KitCopy::payAtSupplier($pack->supplier?->name),
        ];
    }

    /** @return array<string, mixed> */
    public static function order(KitOrder $order): array
    {
        $order->loadMissing(['pack.supplier', 'enrollment.person', 'supplier']);
        $status = $order->status instanceof KitOrderStatus ? $order->status : KitOrderStatus::tryFrom((string) $order->status);
        $supplierName = $order->supplier?->name ?? $order->pack?->supplier?->name;

        return [
            'id' => $order->id,
            'enrollment_id' => $order->enrollment_id,
            'kit_pack_id' => $order->kit_pack_id,
            'status' => $status?->value ?? (string) $order->status,
            'total_amount' => (int) $order->total_amount,
            'commission_amount' => (int) $order->commission_amount,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'student_name' => trim(($order->enrollment?->person?->first_name ?? '').' '.($order->enrollment?->person?->last_name ?? '')),
            'pack' => $order->pack === null ? null : self::pack($order->pack),
            'pay_instruction' => KitCopy::payAtSupplier($supplierName),
        ];
    }
}
