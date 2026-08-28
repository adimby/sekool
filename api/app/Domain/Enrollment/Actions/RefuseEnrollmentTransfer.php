<?php

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Enums\TransferStatus;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;

final class RefuseEnrollmentTransfer
{
    public function byParent(string $transferId, string $parentPersonId, ?string $reason = null): EnrollmentTransfer
    {
        $transfer = $this->loadOpen($transferId);

        if (! ParentAuthorization::isLegalGuardianOf($parentPersonId, $transfer->person_id)) {
            throw new DomainException('Transfert introuvable.', 404);
        }

        return $this->reject($transfer, $reason ?? 'Refus du parent');
    }

    public function byOriginSchool(string $transferId, string $schoolId, ?string $reason = null): EnrollmentTransfer
    {
        $transfer = $this->loadOpen($transferId);

        if ($transfer->origin_school_id !== $schoolId) {
            throw new DomainException('Transfert introuvable.', 404);
        }

        return $this->reject($transfer, $reason ?? 'Refus de l\'école d\'origine');
    }

    private function loadOpen(string $transferId): EnrollmentTransfer
    {
        $transfer = EnrollmentTransfer::query()->find($transferId)
            ?? TenantContext::runWithRlsBypass(fn (): ?EnrollmentTransfer => EnrollmentTransfer::query()->find($transferId));

        if ($transfer === null || in_array($transfer->status, [TransferStatus::Rejected, TransferStatus::Cancelled, TransferStatus::Completed], true)) {
            throw new DomainException('Transfert introuvable.', 404);
        }

        return $transfer;
    }

    private function reject(EnrollmentTransfer $transfer, string $reason): EnrollmentTransfer
    {
        return TenantContext::runWithRlsBypass(function () use ($transfer, $reason): EnrollmentTransfer {
            $transfer->forceFill([
                'status' => TransferStatus::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            Auditor::record('enrollment.transfer_rejected', 'enrollment_transfer', $transfer->id, $transfer->person_id, [
                'reason' => $reason,
            ]);

            return $transfer->refresh();
        });
    }
}
