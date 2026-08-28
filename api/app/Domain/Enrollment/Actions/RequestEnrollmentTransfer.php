<?php

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Enums\TransferStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;

final class RequestEnrollmentTransfer
{
    public function execute(
        Enrollment $originEnrollment,
        string $destinationSchoolId,
        string $destinationSchoolYearId,
        string $actorPersonId,
        ?string $studentNumber = null,
    ): EnrollmentTransfer {
        if ($originEnrollment->status !== EnrollmentStatus::Active) {
            throw new DomainException('L\'inscription d\'origine n\'est pas active.');
        }

        if ($originEnrollment->school_id === $destinationSchoolId) {
            throw new DomainException('L\'élève est déjà inscrit dans cet établissement.');
        }

        $open = TenantContext::runWithRlsBypass(fn (): ?EnrollmentTransfer => EnrollmentTransfer::query()
            ->where('person_id', $originEnrollment->person_id)
            ->where('destination_school_id', $destinationSchoolId)
            ->whereNotIn('status', [
                TransferStatus::Rejected->value,
                TransferStatus::Cancelled->value,
                TransferStatus::Completed->value,
            ])
            ->first());

        if ($open !== null) {
            return $open;
        }

        $transfer = EnrollmentTransfer::query()->create([
            'person_id' => $originEnrollment->person_id,
            'origin_school_id' => $originEnrollment->school_id,
            'origin_enrollment_id' => $originEnrollment->id,
            'destination_school_id' => $destinationSchoolId,
            'requested_by_person_id' => $actorPersonId,
            'status' => TransferStatus::PendingParent,
        ]);

        Auditor::record('enrollment.transfer_requested', 'enrollment_transfer', $transfer->id, $originEnrollment->person_id, [
            'origin_school_id' => $originEnrollment->school_id,
            'destination_school_id' => $destinationSchoolId,
            'destination_school_year_id' => $destinationSchoolYearId,
            'student_number' => $studentNumber,
        ]);

        return $transfer;
    }
}
