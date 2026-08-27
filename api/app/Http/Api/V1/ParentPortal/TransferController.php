<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Enrollment\Actions\ApproveEnrollmentTransfer;
use App\Domain\Enrollment\Actions\RefuseEnrollmentTransfer;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $childIds = ParentAuthorization::authorizedChildIds($request->user()->person_id);
        $rows = EnrollmentTransfer::query()
            ->with(['person', 'originSchool', 'destinationSchool'])
            ->whereIn('person_id', $childIds)
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

    public function approve(Request $request, string $transfer, ApproveEnrollmentTransfer $approve): JsonResponse
    {
        $result = $approve->byParent($transfer, $request->user()->person_id);

        return response()->json(['data' => $result]);
    }

    public function refuse(Request $request, string $transfer, RefuseEnrollmentTransfer $refuse): JsonResponse
    {
        $result = $refuse->byParent(
            $transfer,
            $request->user()->person_id,
            $request->string('reason')->toString() ?: null,
        );

        return response()->json(['data' => $result]);
    }
}
