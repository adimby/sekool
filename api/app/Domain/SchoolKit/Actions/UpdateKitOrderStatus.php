<?php

namespace App\Domain\SchoolKit\Actions;

use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\SchoolKit\Enums\KitOrderStatus;
use App\Domain\SchoolKit\Models\KitOrder;

final class UpdateKitOrderStatus
{
    public function execute(string $schoolId, string $orderId, KitOrderStatus $status): KitOrder
    {
        $order = KitOrder::query()->find($orderId);
        if ($order === null || (string) $order->school_id !== $schoolId) {
            throw new DomainException('Commande introuvable.', 404);
        }

        $order->forceFill(['status' => $status])->save();
        Auditor::record('kit_order.updated', 'kit_order', $order->id, null, [
            'status' => $status->value,
        ]);

        return $order->fresh(['pack.supplier', 'enrollment.person']) ?? $order;
    }
}
