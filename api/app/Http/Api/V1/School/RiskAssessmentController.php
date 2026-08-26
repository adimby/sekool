<?php

namespace App\Http\Api\V1\School;

use App\Domain\Collection\Actions\AssessEnrollmentRisk;
use App\Domain\Collection\Enums\RiskLevel;
use App\Domain\Collection\Models\RiskAssessment;
use App\Domain\Collection\Support\CollectionPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RiskAssessmentController extends Controller
{
    public function show(string $school, string $enrollment, AssessEnrollmentRisk $assess): JsonResponse
    {
        $assessment = RiskAssessment::query()->with('factors')->where('enrollment_id', $enrollment)->first()
            ?? $assess->execute($school, $enrollment);

        return response()->json(['data' => CollectionPayload::assessment($assessment->load('factors'))]);
    }

    public function override(Request $request, string $school, string $enrollment, AssessEnrollmentRisk $assess): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'string', 'in:low,medium,high,critical'],
            'reason' => ['required', 'string', 'max:500'],
            'until' => ['required', 'date', 'after:now'],
        ]);

        $assessment = $assess->override(
            $school,
            $enrollment,
            RiskLevel::from($data['level']),
            $data['reason'],
            new \DateTimeImmutable($data['until']),
            $request->user()->person_id,
        );

        return response()->json(['data' => CollectionPayload::assessment($assessment)]);
    }
}
