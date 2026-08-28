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
        $rows = EnrollmentTransfer::query()
            ->with(['person', 'originSchool', 'destinationSchool'])
            ->visibleToSchool($schoolId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (EnrollmentTransfer $row): array => [
                'id' => $row->id,
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
                'person' => $row->person === null ? null : [
                    'id' => $row->person->id,
                    'first_name' => $row->person->first_name,
                    'last_name' => $row->person->last_name,
                ],
                'origin_school' => $row->originSchool?->name,
                'destination_school' => $row->destinationSchool?->name,
                'parent_approved_at' => $row->parent_approved_at?->toIso8601String(),
            ])->values(),
        ]);
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
