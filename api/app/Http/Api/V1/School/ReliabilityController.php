<?php

namespace App\Http\Api\V1\School;

use App\Domain\Collection\Support\CollectionPayload;
use App\Domain\Collection\Support\FamilyRecipients;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Family\Support\FamilyHasSchoolEnrollment;
use App\Domain\Reliability\Actions\CompareReliabilityScore;
use App\Domain\Reliability\Actions\ComputeFamilyReliability;
use App\Domain\Reliability\Actions\ComputeRelationshipHealth;
use App\Domain\Reliability\Actions\ComputeSchoolReliability;
use App\Domain\Reliability\Models\ReliabilityScore;
use App\Domain\Reliability\Support\FamilyReliabilityCalculator;
use App\Domain\Reliability\Support\RelationshipHealthCalculator;
use App\Domain\Reliability\Support\ReliabilityIndexes;
use App\Domain\Reliability\Support\SchoolReliabilityCalculator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ReliabilityController extends Controller
{
    public function __construct(
        private readonly ComputeSchoolReliability $school,
        private readonly ComputeFamilyReliability $family,
        private readonly ComputeRelationshipHealth $relationship,
        private readonly CompareReliabilityScore $compare,
    ) {}

    public function school(string $school): JsonResponse
    {
        return response()->json(['data' => CollectionPayload::reliability($this->schoolScore($school))]);
    }

    public function schoolCompare(string $school): JsonResponse
    {
        $stored = $this->schoolScore($school);

        return response()->json(['data' => $this->compare->execute($stored, $this->school->preview($school))]);
    }

    public function familyCompare(string $school, string $family): JsonResponse
    {
        if (! FamilyHasSchoolEnrollment::exists($family)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $stored = $this->familyScore($school, $family);

        return response()->json(['data' => $this->compare->execute($stored, $this->family->preview($school, $family))]);
    }

    public function relationship(string $school, string $family): JsonResponse
    {
        if (! FamilyHasSchoolEnrollment::exists($family)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => CollectionPayload::reliability($this->relationshipScore($school, $family))]);
    }

    public function relationshipCompare(string $school, string $family): JsonResponse
    {
        if (! FamilyHasSchoolEnrollment::exists($family)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $stored = $this->relationshipScore($school, $family);

        return response()->json(['data' => $this->compare->execute($stored, $this->relationship->preview($school, $family))]);
    }

    public function overview(string $school): JsonResponse
    {
        $rows = [];
        $enrollments = Enrollment::query()
            ->with('person')
            ->where('status', EnrollmentStatus::Active)
            ->get();

        foreach ($enrollments as $enrollment) {
            $familyId = FamilyRecipients::familyIdForStudent((string) $enrollment->person_id);
            if ($familyId === null) {
                continue;
            }
            if (! isset($rows[$familyId])) {
                $rows[$familyId] = [
                    'family_id' => $familyId,
                    'students' => [],
                    'family_reliability' => CollectionPayload::reliability($this->familyScore($school, $familyId)),
                    'relationship_health' => CollectionPayload::reliability($this->relationshipScore($school, $familyId)),
                ];
            }
            if ($enrollment->person !== null) {
                $rows[$familyId]['students'][] = [
                    'id' => $enrollment->person->id,
                    'first_name' => $enrollment->person->first_name,
                    'last_name' => $enrollment->person->last_name,
                ];
            }
        }

        return response()->json([
            'data' => [
                'school' => CollectionPayload::reliability($this->schoolScore($school)),
                'families' => array_values($rows),
            ],
        ]);
    }

    private function schoolScore(string $schoolId): ReliabilityScore
    {
        return $this->current(
            ReliabilityIndexes::SUBJECT_SCHOOL,
            $schoolId,
            ReliabilityIndexes::SCHOOL,
            SchoolReliabilityCalculator::VERSION,
        ) ?? $this->school->execute($schoolId);
    }

    private function familyScore(string $schoolId, string $familyId): ReliabilityScore
    {
        return $this->current(
            ReliabilityIndexes::SUBJECT_FAMILY,
            $familyId,
            ReliabilityIndexes::FAMILY,
            FamilyReliabilityCalculator::VERSION,
        ) ?? $this->family->execute($schoolId, $familyId);
    }

    private function relationshipScore(string $schoolId, string $familyId): ReliabilityScore
    {
        return $this->current(
            ReliabilityIndexes::SUBJECT_RELATIONSHIP,
            $familyId,
            ReliabilityIndexes::RELATIONSHIP,
            RelationshipHealthCalculator::VERSION,
        ) ?? $this->relationship->execute($schoolId, $familyId);
    }

    private function current(string $subjectType, string $subjectId, string $indexType, string $version): ?ReliabilityScore
    {
        return ReliabilityScore::query()
            ->with('factors')
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('index_type', $indexType)
            ->where('calculator_version', $version)
            ->first();
    }
}
