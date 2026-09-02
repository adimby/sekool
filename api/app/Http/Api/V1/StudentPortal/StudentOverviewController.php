<?php

namespace App\Http\Api\V1\StudentPortal;

use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Models\PaymentAllocation;
use App\Domain\Finance\Support\InvoicePayload;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Models\PersonRole;
use App\Domain\Identity\Support\PersonPayload;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentOverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $personId = $request->user()->person_id;

        $isStudent = PersonRole::query()
            ->where('person_id', $personId)
            ->where('role', PersonRoleType::Student)
            ->whereNull('ended_at')
            ->exists();

        if (! $isStudent) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $person = $request->user()->person;
        if ($person === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return TenantContext::runWithRlsBypass(function () use ($person, $personId): JsonResponse {
            $enrollment = Enrollment::query()
                ->withoutGlobalScopes()
                ->with(['school', 'classroom'])
                ->where('person_id', $personId)
                ->where('status', 'active')
                ->orderByDesc('enrolled_on')
                ->first();

            $invoice = null;
            $payments = collect();
            if ($enrollment !== null) {
                $invoice = Invoice::query()
                    ->withoutGlobalScopes()
                    ->with('installments')
                    ->where('enrollment_id', $enrollment->id)
                    ->where('status', '!=', 'cancelled')
                    ->latest('issued_on')
                    ->first();

                if ($invoice !== null) {
                    $installmentIds = $invoice->installments->pluck('id');
                    $paymentIds = PaymentAllocation::query()
                        ->withoutGlobalScopes()
                        ->whereIn('installment_id', $installmentIds)
                        ->pluck('payment_id');
                    $payments = Payment::query()
                        ->withoutGlobalScopes()
                        ->with('receipt')
                        ->whereIn('id', $paymentIds)
                        ->orderByDesc('received_on')
                        ->get();
                }
            }

            $from = now()->subDays(14)->toDateString();
            $to = now()->toDateString();
            $attendance = $enrollment === null
                ? collect()
                : AttendanceRecord::query()
                    ->withoutGlobalScopes()
                    ->with('timetableSlot')
                    ->where('enrollment_id', $enrollment->id)
                    ->whereBetween('date', [$from, $to])
                    ->orderByDesc('date')
                    ->orderBy('id')
                    ->get();

            return response()->json([
                'person' => PersonPayload::forParent($person),
                'enrollment' => $enrollment === null ? null : [
                    'id' => $enrollment->id,
                    'student_number' => $enrollment->student_number,
                    'status' => $enrollment->status->value,
                    'school' => $enrollment->school === null ? null : [
                        'id' => $enrollment->school->id,
                        'name' => $enrollment->school->name,
                    ],
                    'classroom' => $enrollment->classroom === null ? null : [
                        'id' => $enrollment->classroom->id,
                        'name' => $enrollment->classroom->name,
                    ],
                ],
                'attendance' => $attendance->map(fn (AttendanceRecord $row): array => [
                    'id' => $row->id,
                    'date' => $row->date?->toDateString(),
                    'session' => $row->session->value,
                    'status' => $row->status->value,
                    'reason' => $row->reason,
                    'justification' => $row->justification,
                    'subject' => $row->timetableSlot?->subject,
                    'starts_at' => $row->timetableSlot === null
                        ? null
                        : substr((string) $row->timetableSlot->starts_at, 0, 5),
                ])->values(),
                'finance' => [
                    'remaining_amount' => $invoice?->remainingAmount() ?? 0,
                    'invoice' => $invoice === null ? null : InvoicePayload::make($invoice),
                    'payments' => $payments->map(fn (Payment $payment): array => [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'method' => $payment->method->value,
                        'received_on' => $payment->received_on?->toDateString(),
                        'receipt_number' => $payment->receipt?->number,
                    ])->values(),
                ],
            ]);
        });
    }
}
