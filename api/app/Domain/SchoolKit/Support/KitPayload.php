<?php

namespace App\Domain\SchoolKit\Support;

use App\Domain\SchoolKit\Enums\KitFulfillment;
use App\Domain\SchoolKit\Enums\KitOrderStatus;
use App\Domain\SchoolKit\Enums\KitPackTier;
use App\Domain\SchoolKit\Enums\KitPriceSource;
use App\Domain\SchoolKit\Models\KitDefinition;
use App\Domain\SchoolKit\Models\KitNeed;
use App\Domain\SchoolKit\Models\KitOrder;
use App\Domain\SchoolKit\Models\KitPack;
use App\Domain\SchoolKit\Models\KitPackItem;

final class KitPayload
{
    /** @return array<string, mixed> */
    public static function definition(KitDefinition $definition): array
    {
        $definition->loadMissing(['needs', 'packs.supplier', 'packs.items', 'gradeLevel']);
        $source = $definition->price_source instanceof KitPriceSource
            ? $definition->price_source
            : KitPriceSource::tryFrom((string) $definition->price_source);

        return [
            'id' => $definition->id,
            'name' => $definition->name,
            'status' => $definition->status,
            'school_year_id' => $definition->school_year_id,
            'grade_level_id' => $definition->grade_level_id,
            'grade_level' => $definition->gradeLevel?->name,
            'price_source' => $source?->value ?? 'supplier',
            'price_source_label' => $source?->label() ?? KitPriceSource::Supplier->label(),
            'copied_from_id' => $definition->copied_from_id,
            'needs' => $definition->needs->map(fn (KitNeed $need): array => self::need($need, $definition))->values(),
            'packs' => $definition->packs->map(fn (KitPack $pack): array => self::pack($pack))->values(),
            'choice_copy' => KitCopy::parentChoice(),
        ];
    }

    /** @return array<string, mixed> */
    public static function need(KitNeed $need, KitDefinition $definition): array
    {
        $offers = [];
        foreach ($definition->packs as $pack) {
            $item = $pack->items->firstWhere('need_id', $need->id);
            if (! $item instanceof KitPackItem) {
                continue;
            }
            $tier = $pack->tier instanceof KitPackTier ? $pack->tier : KitPackTier::parse((string) $pack->tier);
            $offers[] = [
                'tier' => $tier?->value ?? (string) $pack->tier,
                'tier_label' => $tier?->label() ?? (string) $pack->tier,
                'brand' => $item->brand,
                'unit_amount' => (int) $item->unit_amount,
                'quantity' => (int) $item->quantity,
                'line_amount' => (int) $item->unit_amount * (int) $item->quantity,
            ];
        }

        return [
            'id' => $need->id,
            'label' => $need->label,
            'quantity' => (int) $need->quantity,
            'notes' => $need->notes,
            'offers' => $offers,
        ];
    }

    /** @return array<string, mixed> */
    public static function pack(KitPack $pack): array
    {
        $pack->loadMissing(['supplier', 'items']);
        $tier = $pack->tier instanceof KitPackTier ? $pack->tier : KitPackTier::parse((string) $pack->tier);

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
            'items' => $pack->items->map(fn (KitPackItem $item): array => [
                'id' => $item->id,
                'need_id' => $item->need_id,
                'brand' => $item->brand,
                'unit_amount' => (int) $item->unit_amount,
                'quantity' => (int) $item->quantity,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    public static function order(KitOrder $order): array
    {
        $order->loadMissing(['pack.supplier', 'enrollment.person', 'supplier', 'definition']);
        $status = $order->status instanceof KitOrderStatus ? $order->status : KitOrderStatus::tryFrom((string) $order->status);
        $fulfillment = $order->fulfillment instanceof KitFulfillment
            ? $order->fulfillment
            : KitFulfillment::tryFrom((string) ($order->fulfillment ?? KitFulfillment::Partner->value));
        $supplierName = $order->supplier?->name ?? $order->pack?->supplier?->name;

        return [
            'id' => $order->id,
            'enrollment_id' => $order->enrollment_id,
            'kit_definition_id' => $order->kit_definition_id,
            'kit_pack_id' => $order->kit_pack_id,
            'fulfillment' => $fulfillment?->value ?? KitFulfillment::Partner->value,
            'fulfillment_label' => $fulfillment?->label() ?? KitFulfillment::Partner->label(),
            'status' => $status?->value ?? (string) $order->status,
            'status_label' => $status?->label() ?? (string) $order->status,
            'total_amount' => (int) $order->total_amount,
            'commission_amount' => (int) $order->commission_amount,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'student_name' => trim(($order->enrollment?->person?->first_name ?? '').' '.($order->enrollment?->person?->last_name ?? '')),
            'pack' => $order->pack === null ? null : self::pack($order->pack),
            'pay_instruction' => $fulfillment === KitFulfillment::Self
                ? 'Le parent fournit les articles. FANABE n’encaisse pas.'
                : KitCopy::payAtSupplier($supplierName),
        ];
    }
}
