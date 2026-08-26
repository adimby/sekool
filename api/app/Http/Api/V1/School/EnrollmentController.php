<?php

namespace App\Http\Api\V1\School;

use App\Domain\Enrollment\Actions\EnrollStudent;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EnrollmentController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Enrollment::query()
            ->with(['person', 'classroom'])
            ->orderBy('enrolled_on', 'desc')
            ->get();

        $invoices = Invoice::query()
            ->whereIn('enrollment_id', $rows->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->get()
            ->keyBy('enrollment_id');

        return response()->json([
            'data' => $rows->map(function (Enrollment $enrollment) use ($invoices): array {
                $invoice = $invoices->get($enrollment->id);

                return [
                    'id' => $enrollment->id,
                    'person_id' => $enrollment->person_id,
                    'school_year_id' => $enrollment->school_year_id,
                    'classroom_id' => $enrollment->classroom_id,
                    'student_number' => $enrollment->student_number,
                    'status' => $enrollment->status->value,
                    'enrolled_on' => $enrollment->enrolled_on?->toDateString(),
                    'person' => $enrollment->person === null ? null : [
                        'id' => $enrollment->person->id,
                        'public_id' => $enrollment->person->public_id,
                        'first_name' => $enrollment->person->first_name,
                        'last_name' => $enrollment->person->last_name,
                    ],
                    'classroom' => $enrollment->classroom === null ? null : [
                        'id' => $enrollment->classroom->id,
                        'name' => $enrollment->classroom->name,
                    ],
                    'invoice' => $invoice === null ? null : [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'net_amount' => $invoice->net_amount,
                        'remaining_amount' => $invoice->remainingAmount(),
                        'status' => $invoice->status->value,
                    ],
                ];
            })->values(),
        ]);
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
