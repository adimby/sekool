<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Enrollment\Actions\ApproveEnrollmentTransfer;
use App\Domain\Enrollment\Actions\RefuseEnrollmentTransfer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransferController extends Controller
{
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
