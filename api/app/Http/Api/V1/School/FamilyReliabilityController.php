<?php

namespace App\Http\Api\V1\School;

use App\Domain\Collection\Support\CollectionPayload;
use App\Domain\Collection\Support\FamilyRecipients;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Reliability\Actions\ComputeFamilyReliability;
use App\Domain\Reliability\Models\ReliabilityScore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class FamilyReliabilityController extends Controller
{
    public function __invoke(string $school, string $family, ComputeFamilyReliability $compute): JsonResponse
    {
        $studentIds = Enrollment::query()->pluck('person_id');
        $linked = FamilyMember::query()
            ->where('family_id', $family)
            ->whereIn('person_id', $studentIds)
            ->whereNull('left_at')
            ->exists();

        if (! $linked) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $score = ReliabilityScore::query()
            ->with('factors')
            ->where('subject_type', 'family')
            ->where('subject_id', $family)
            ->where('index_type', 'family')
            ->first()
            ?? $compute->execute($school, $family);

        return response()->json(['data' => CollectionPayload::reliability($score->load('factors'))]);
    }
}
