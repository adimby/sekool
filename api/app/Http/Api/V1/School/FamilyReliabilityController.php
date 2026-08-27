<?php

namespace App\Http\Api\V1\School;

use App\Domain\Collection\Support\CollectionPayload;
use App\Domain\Family\Support\FamilyHasSchoolEnrollment;
use App\Domain\Reliability\Actions\ComputeFamilyReliability;
use App\Domain\Reliability\Models\ReliabilityScore;
use App\Domain\Reliability\Support\FamilyReliabilityCalculator;
use App\Domain\Reliability\Support\ReliabilityIndexes;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class FamilyReliabilityController extends Controller
{
    public function __invoke(string $school, string $family, ComputeFamilyReliability $compute): JsonResponse
    {
        if (! FamilyHasSchoolEnrollment::exists($family)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $score = ReliabilityScore::query()
            ->with('factors')
            ->where('subject_type', ReliabilityIndexes::SUBJECT_FAMILY)
            ->where('subject_id', $family)
            ->where('index_type', ReliabilityIndexes::FAMILY)
            ->where('calculator_version', FamilyReliabilityCalculator::VERSION)
            ->first()
            ?? $compute->execute($school, $family);

        return response()->json(['data' => CollectionPayload::reliability($score->load('factors'))]);
    }
}
