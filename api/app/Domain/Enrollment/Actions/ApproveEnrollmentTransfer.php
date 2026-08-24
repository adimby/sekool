<?php

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Enums\TransferStatus;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;

final class ApproveEnrollmentTransfer
{
    public function byParent(string $transferId, string $parentPersonId): EnrollmentTransfer
    {
        $transfer = $this->loadOpen($transferId);

        if (! ParentAuthorization::isLegalGuardianOf($parentPersonId, $transfer->person_id)) {
            throw new DomainException('Transfert introuvable.', 404);
        }

        if ($transfer->parent_approved_at !== null) {
            return $this->maybeComplete($transfer);
        }

        return TenantContext::runWithRlsBypass(function () use ($transfer, $parentPersonId): EnrollmentTransfer {
            $transfer->forceFill([
                'parent_approved_at' => now(),
                'parent_approved_by_person_id' => $parentPersonId,
                'status' => $transfer->origin_school_approved_at !== null
                    ? TransferStatus::Approved
                    : TransferStatus::PendingOriginSchool,
            ])->save();

            Auditor::record('enrollment.transfer_parent_approved', 'enrollment_transfer', $transfer->id, $transfer->person_id);

            return $this->maybeComplete($transfer->refresh());
        });
    }

    public function byOriginSchool(string $transferId, string $schoolId, string $actorPersonId): EnrollmentTransfer
    {
        $transfer = $this->loadOpen($transferId);

        if ($transfer->origin_school_id !== $schoolId) {
            throw new DomainException('Transfert introuvable.', 404);
        }

        if ($transfer->origin_school_approved_at !== null) {
            return $this->maybeComplete($transfer);
        }

        $transfer->forceFill([
            'origin_school_approved_at' => now(),
            'origin_approved_by_person_id' => $actorPersonId,
            'status' => $transfer->parent_approved_at !== null
                ? TransferStatus::Approved
                : TransferStatus::PendingParent,
        ])->save();

        Auditor::record('enrollment.transfer_origin_approved', 'enrollment_transfer', $transfer->id, $transfer->person_id);

        return $this->maybeComplete($transfer->refresh());
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

    private function maybeComplete(EnrollmentTransfer $transfer): EnrollmentTransfer
    {
        if ($transfer->parent_approved_at === null || $transfer->origin_school_approved_at === null) {
            return $transfer;
        }

        return app(CompleteEnrollmentTransfer::class)->execute($transfer);
    }
}
