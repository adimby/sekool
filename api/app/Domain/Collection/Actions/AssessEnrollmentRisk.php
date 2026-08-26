<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Enums\RiskLevel;
use App\Domain\Collection\Models\RiskAssessment;
use App\Domain\Collection\Models\RiskFactor;
use App\Domain\Collection\Support\EnrollmentInstallments;
use App\Domain\Collection\Support\RiskCalculator;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Platform\Exceptions\DomainException;
use DateTimeInterface;

final class AssessEnrollmentRisk
{
    public function __construct(private readonly RiskCalculator $calculator) {}

    public function execute(string $schoolId, string $enrollmentId, ?DateTimeInterface $asOf = null): RiskAssessment
    {
        $enrollment = Enrollment::query()->find($enrollmentId);
        if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
            throw new DomainException('Inscription introuvable.', 404);
        }

        $asOf ??= now();
        $snapshot = EnrollmentInstallments::snapshot($enrollmentId);
        $assessed = $this->calculator->assess($snapshot, $asOf);

        $payerId = Invoice::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('status', '!=', 'cancelled')
            ->value('payer_account_id');

        $assessment = RiskAssessment::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'enrollment_id' => $enrollmentId,
            ],
            [
                'payer_account_id' => $payerId,
                'level' => $assessed['level'],
                'outstanding_amount' => $assessed['outstanding_amount'],
                'days_overdue' => $assessed['days_overdue'],
                'on_time_ratio' => $assessed['on_time_ratio'],
                'calculator_version' => $assessed['calculator_version'],
                'computed_at' => now(),
            ],
        );

        RiskFactor::query()->where('risk_assessment_id', $assessment->id)->delete();
        foreach ($assessed['factors'] as $factor) {
            RiskFactor::query()->create([
                'school_id' => $schoolId,
                'risk_assessment_id' => $assessment->id,
                'factor_key' => $factor['factor_key'],
                'human_label' => $factor['human_label'],
                'contribution' => $factor['contribution'],
                'evidence' => $factor['evidence'],
            ]);
        }

        return $assessment->refresh()->load('factors');
    }

    public function override(
        string $schoolId,
        string $enrollmentId,
        RiskLevel $level,
        string $reason,
        DateTimeInterface $until,
        string $actorPersonId,
    ): RiskAssessment {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Une dérogation exige un motif.');
        }

        $assessment = $this->execute($schoolId, $enrollmentId);
        $assessment->forceFill([
            'manual_override_level' => $level,
            'override_reason' => $reason,
            'override_until' => $until,
            'override_by_person_id' => $actorPersonId,
        ])->save();

        return $assessment->refresh()->load('factors');
    }
}
