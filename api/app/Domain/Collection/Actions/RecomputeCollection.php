<?php

namespace App\Domain\Collection\Actions;

use App\Domain\Collection\Support\FamilyRecipients;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\Reliability\Actions\ComputeFamilyReliability;
use App\Domain\Reliability\Actions\ComputeRelationshipHealth;
use App\Domain\Reliability\Actions\ComputeSchoolReliability;
use App\Domain\School\Models\School;
use App\Domain\Workflow\Actions\EnsureWorkflowCatalog;
use App\Domain\Workflow\Actions\EvaluateWorkflows;

final class RecomputeCollection
{
    public function __construct(
        private readonly AssessEnrollmentRisk $risk,
        private readonly ComputeCollectionForecast $forecast,
        private readonly EvaluateWorkflows $workflows,
        private readonly ComputeFamilyReliability $familyReliability,
        private readonly ComputeRelationshipHealth $relationshipHealth,
        private readonly ComputeSchoolReliability $schoolReliability,
        private readonly EnsureWorkflowCatalog $catalog,
    ) {}

    public function execute(?string $schoolId = null, bool $live = false): void
    {
        $schools = $schoolId === null
            ? School::query()->get()
            : School::query()->where('id', $schoolId)->get();

        foreach ($schools as $school) {
            TenantContext::run(TenantContext::forSchool((string) $school->id), function () use ($school, $live): void {
                $this->catalog->execute((string) $school->id, $live);
                $familyIds = [];
                foreach (Enrollment::query()->where('status', EnrollmentStatus::Active)->get() as $enrollment) {
                    $this->risk->execute((string) $school->id, (string) $enrollment->id);
                    $familyId = FamilyRecipients::familyIdForStudent((string) $enrollment->person_id);
                    if ($familyId !== null) {
                        $familyIds[$familyId] = $familyId;
                    }
                }
                $this->forecast->execute((string) $school->id);
                $this->workflows->execute((string) $school->id, forceLive: $live);
                foreach ($familyIds as $id) {
                    $this->familyReliability->execute((string) $school->id, $id);
                    $this->relationshipHealth->execute((string) $school->id, $id);
                }
                $this->schoolReliability->execute((string) $school->id);
            });
        }
    }
}
