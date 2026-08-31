<?php

namespace App\Domain\Enrollment\Actions;

use App\Domain\Certificate\Actions\IssueWithdrawalCertificate;
use App\Domain\Certificate\Models\Certificate;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentStatusChange;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class WithdrawEnrollment
{
    public function __construct(private readonly IssueWithdrawalCertificate $issue) {}

    /**
     * @return array{enrollment: Enrollment, certificate: Certificate, token: string, verify_url: string}
     */
    public function execute(string $schoolId, string $enrollmentId, string $actorPersonId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Indiquez le motif de radiation.');
        }

        return DB::transaction(function () use ($schoolId, $enrollmentId, $actorPersonId, $reason): array {
            $enrollment = Enrollment::query()->with(['person', 'classroom', 'schoolYear', 'school'])->find($enrollmentId);
            if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
                throw new DomainException('Inscription introuvable.', 404);
            }
            if ($enrollment->status !== EnrollmentStatus::Active) {
                throw new DomainException('Seule une inscription active peut être radiée.');
            }

            $from = $enrollment->status;
            $enrollment->forceFill([
                'status' => EnrollmentStatus::Withdrawn,
                'ended_on' => now()->toDateString(),
                'exit_reason' => $reason,
            ])->save();

            EnrollmentStatusChange::query()->create([
                'school_id' => $schoolId,
                'enrollment_id' => $enrollment->id,
                'from_status' => $from->value,
                'to_status' => EnrollmentStatus::Withdrawn->value,
                'reason' => $reason,
                'occurred_at' => now(),
                'actor_person_id' => $actorPersonId,
            ]);

            Auditor::record('enrollment.withdrawn', 'enrollment', $enrollment->id, $enrollment->person_id, [
                'reason' => $reason,
            ]);

            $issued = $this->issue->execute($schoolId, (string) $enrollment->id, $actorPersonId);

            return [
                'enrollment' => $enrollment->fresh(['person', 'classroom']),
                'certificate' => $issued['certificate'],
                'token' => $issued['token'],
                'verify_url' => $issued['verify_url'],
            ];
        });
    }
}
