<?php

namespace App\Domain\Enrollment\Actions;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Enums\TransferStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentStatusChange;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Identity\Actions\GrantSchoolPersonLink;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Support\Facades\DB;

final class CompleteEnrollmentTransfer
{
    public function execute(EnrollmentTransfer $transfer): EnrollmentTransfer
    {
        if ($transfer->parent_approved_at === null || $transfer->origin_school_approved_at === null) {
            throw new DomainException('Le transfert exige la validation du parent et de l\'école d\'origine.');
        }

        if ($transfer->status === TransferStatus::Completed) {
            return $transfer;
        }

        return TenantContext::runWithRlsBypass(function () use ($transfer): EnrollmentTransfer {
            return DB::transaction(function () use ($transfer): EnrollmentTransfer {
                $origin = Enrollment::query()->withoutGlobalScopes()->findOrFail($transfer->origin_enrollment_id);

                if ($origin->status !== EnrollmentStatus::Active) {
                    throw new DomainException('L\'inscription d\'origine n\'est plus active.');
                }

                $origin->forceFill([
                    'status' => EnrollmentStatus::TransferredOut,
                    'ended_on' => now()->toDateString(),
                    'exit_reason' => 'transfer',
                ])->save();

                EnrollmentStatusChange::query()->withoutGlobalScopes()->create([
                    'school_id' => $origin->school_id,
                    'enrollment_id' => $origin->id,
                    'from_status' => EnrollmentStatus::Active->value,
                    'to_status' => EnrollmentStatus::TransferredOut->value,
                    'reason' => 'transfer',
                    'occurred_at' => now(),
                    'actor_person_id' => $transfer->origin_approved_by_person_id,
                ]);

                $yearId = $this->destinationYearId($transfer, $origin);

                $destination = Enrollment::query()->withoutGlobalScopes()->create([
                    'school_id' => $transfer->destination_school_id,
                    'school_year_id' => $yearId,
                    'person_id' => $transfer->person_id,
                    'status' => EnrollmentStatus::Active,
                    'enrolled_on' => now()->toDateString(),
                    'source_type' => 'native',
                ]);

                EnrollmentStatusChange::query()->withoutGlobalScopes()->create([
                    'school_id' => $destination->school_id,
                    'enrollment_id' => $destination->id,
                    'from_status' => null,
                    'to_status' => EnrollmentStatus::Active->value,
                    'reason' => 'transfer',
                    'occurred_at' => now(),
                    'actor_person_id' => $transfer->requested_by_person_id,
                ]);

                app(GrantSchoolPersonLink::class)->execute(
                    $destination->school_id,
                    $transfer->person_id,
                    SchoolPersonLinkKind::Student,
                    SchoolPersonLinkSource::Enrollment,
                    grantsContactAccess: false,
                );

                $transfer->forceFill([
                    'destination_enrollment_id' => $destination->id,
                    'status' => TransferStatus::Completed,
                    'completed_at' => now(),
                ])->save();

                Auditor::record('enrollment.transfer_completed', 'enrollment_transfer', $transfer->id, $transfer->person_id, [
                    'origin_enrollment_id' => $origin->id,
                    'destination_enrollment_id' => $destination->id,
                ]);
                TrustEvent::emit('person', $transfer->person_id, 'enrollment.transferred_out', $origin->school_id, 'enrollment_transfer', $transfer->id);
                TrustEvent::emit('person', $transfer->person_id, 'enrollment.activated', $destination->school_id, 'enrollment_transfer', $transfer->id);

                return $transfer->refresh();
            });
        });
    }

    private function destinationYearId(EnrollmentTransfer $transfer, Enrollment $origin): string
    {
        $current = SchoolYear::query()
            ->withoutGlobalScopes()
            ->where('school_id', $transfer->destination_school_id)
            ->where('is_current', true)
            ->first();

        if ($current !== null) {
            return $current->id;
        }

        $sameLabel = SchoolYear::query()
            ->withoutGlobalScopes()
            ->where('school_id', $transfer->destination_school_id)
            ->where('label', $origin->schoolYear()->withoutGlobalScopes()->first()?->label)
            ->first();

        if ($sameLabel !== null) {
            return $sameLabel->id;
        }

        throw new DomainException('Aucune année scolaire d\'accueil n\'est disponible.');
    }
}
