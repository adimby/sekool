<?php

namespace App\Http\Api\V1\School;

use App\Domain\Certificate\Support\CertificatePayload;
use App\Domain\Enrollment\Actions\WithdrawEnrollment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EnrollmentWithdrawController extends Controller
{
    public function __invoke(Request $request, string $school, string $enrollment, WithdrawEnrollment $withdraw): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $result = $withdraw->execute(
            $school,
            $enrollment,
            (string) $request->user()->person_id,
            $data['reason'],
        );

        $certificate = CertificatePayload::staff($result['certificate'], $result['verify_url']);
        $certificate['token'] = $result['token'];

        return response()->json([
            'data' => [
                'id' => $result['enrollment']->id,
                'status' => $result['enrollment']->status->value,
                'ended_on' => $result['enrollment']->ended_on?->toDateString(),
                'exit_reason' => $result['enrollment']->exit_reason,
                'certificate' => $certificate,
            ],
        ]);
    }
}
