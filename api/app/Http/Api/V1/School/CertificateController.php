<?php

namespace App\Http\Api\V1\School;

use App\Domain\Certificate\Actions\IssueEnrollmentCertificate;
use App\Domain\Certificate\Actions\RevokeCertificate;
use App\Domain\Certificate\Models\Certificate;
use App\Domain\Certificate\Support\CertificatePayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CertificateController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Certificate::query()
            ->with(['enrollment.person', 'enrollment.classroom', 'subject'])
            ->orderByDesc('issued_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Certificate $row): array => CertificatePayload::staff($row))->values(),
        ]);
    }

    public function store(Request $request, string $school, string $enrollment, IssueEnrollmentCertificate $issue): JsonResponse
    {
        $result = $issue->execute($school, $enrollment, (string) $request->user()->person_id);

        $payload = CertificatePayload::staff($result['certificate'], $result['verify_url']);
        $payload['token'] = $result['token'];

        return response()->json(['data' => $payload], 201);
    }

    public function revoke(Request $request, string $school, string $certificate, RevokeCertificate $revoke): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $row = $revoke->execute($school, $certificate, $data['reason']);

        return response()->json(['data' => CertificatePayload::staff($row)]);
    }
}
