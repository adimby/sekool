<?php

namespace App\Http\Api\V1\School;

use App\Domain\Enrollment\Actions\EnrollStudent;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EnrollmentController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Enrollment::query()->orderBy('enrolled_on', 'desc')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, EnrollStudent $enroll): JsonResponse
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'person_id' => ['required', 'uuid'],
            'student_number' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $enroll->execute(
            schoolId: TenantContext::requireSchoolId(),
            schoolYearId: $data['school_year_id'],
            studentPersonId: $data['person_id'],
            actorPersonId: $request->user()->person_id,
            studentNumber: $data['student_number'] ?? null,
        );

        if ($result instanceof EnrollmentTransfer) {
            return response()->json([
                'status' => 'transfer_pending',
                'transfer' => [
                    'id' => $result->id,
                    'status' => $result->status->value,
                ],
            ], 202);
        }

        return response()->json(['data' => $result], 201);
    }
}
