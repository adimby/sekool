<?php

namespace App\Http\Api\V1\School;

use App\Domain\Enrollment\Actions\ApproveEnrollmentTransfer;
use App\Domain\Enrollment\Actions\RefuseEnrollmentTransfer;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransferController extends Controller
{
    public function index(): JsonResponse
    {
        $schoolId = TenantContext::requireSchoolId();
        $rows = EnrollmentTransfer::query()->visibleToSchool($schoolId)->orderByDesc('created_at')->get();

        return response()->json(['data' => $rows]);
    }

    public function approve(Request $request, string $school, string $transfer, ApproveEnrollmentTransfer $approve): JsonResponse
    {
        $result = $approve->byOriginSchool($transfer, TenantContext::requireSchoolId(), $request->user()->person_id);

        return response()->json(['data' => $result]);
    }

    public function refuse(Request $request, string $school, string $transfer, RefuseEnrollmentTransfer $refuse): JsonResponse
    {
        $result = $refuse->byOriginSchool(
            $transfer,
            TenantContext::requireSchoolId(),
            $request->string('reason')->toString() ?: null,
        );

        return response()->json(['data' => $result]);
    }
}
