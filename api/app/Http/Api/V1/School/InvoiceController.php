<?php

namespace App\Http\Api\V1\School;

use App\Domain\Finance\Actions\GenerateInvoice;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Support\InvoicePayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceController extends Controller
{
    public function schedules(): JsonResponse
    {
        $schedules = FeeSchedule::query()->with('items')->orderBy('name')->get();

        return response()->json(['data' => $schedules]);
    }

    public function show(string $school, string $enrollment): JsonResponse
    {
        $invoice = Invoice::query()
            ->where('enrollment_id', $enrollment)
            ->where('status', '!=', 'cancelled')
            ->latest('issued_on')
            ->first();

        if ($invoice === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => InvoicePayload::make($invoice)]);
    }

    public function store(Request $request, string $school, string $enrollment, GenerateInvoice $generate): JsonResponse
    {
        $data = $request->validate([
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $generate->execute(
            schoolId: $school,
            enrollmentId: $enrollment,
            actorPersonId: $request->user()->person_id,
            discountAmount: (int) ($data['discount_amount'] ?? 0),
            discountReason: $data['discount_reason'] ?? null,
        );

        return response()->json(
            ['data' => InvoicePayload::make($result['invoice']), 'created' => $result['created']],
            $result['created'] ? 201 : 200,
        );
    }
}
