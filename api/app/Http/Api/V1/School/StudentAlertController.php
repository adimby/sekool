<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\AcknowledgeStudentAlert;
use App\Domain\Academic\Actions\DetectStudentAlerts;
use App\Domain\Academic\Enums\StudentAlertStatus;
use App\Domain\Academic\Models\StudentAlert;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentAlertController extends Controller
{
    public function index(DetectStudentAlerts $detect): JsonResponse
    {
        $detect->execute();

        $rows = StudentAlert::query()
            ->with('enrollment.person')
            ->where('status', StudentAlertStatus::Open)
            ->orderByDesc('detected_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (StudentAlert $row): array => $row->toPayload())->values(),
        ]);
    }

    public function acknowledge(Request $request, string $school, string $alert, AcknowledgeStudentAlert $acknowledge): JsonResponse
    {
        $row = StudentAlert::query()->find($alert);
        if ($row === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'data' => $acknowledge->execute($row, (string) $request->user()->person_id)->toPayload(),
        ]);
    }
}
