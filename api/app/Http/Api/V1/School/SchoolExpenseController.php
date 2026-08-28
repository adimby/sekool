<?php

namespace App\Http\Api\V1\School;

use App\Domain\Finance\Actions\RecordSchoolExpense;
use App\Domain\Finance\Enums\ExpenseCategory;
use App\Domain\Finance\Enums\ExpenseKind;
use App\Domain\Finance\Models\SchoolExpense;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SchoolExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $yearId = $request->query('school_year_id');

        $rows = SchoolExpense::query()
            ->when(is_string($yearId) && $yearId !== '', fn ($query) => $query->where('school_year_id', $yearId))
            ->orderByDesc('spent_on')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (SchoolExpense $row): array => $this->serialize($row))->values(),
            'total_amount' => $rows->sum(fn (SchoolExpense $row): int => (int) $row->amount),
        ]);
    }

    public function store(Request $request, RecordSchoolExpense $record): JsonResponse
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'kind' => ['required', 'string'],
            'label' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'spent_on' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $expense = $record->execute(
            (string) $request->route('school'),
            (string) $request->user()->person_id,
            $data,
        );

        return response()->json(['data' => $this->serialize($expense)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SchoolExpense $row): array
    {
        $kind = $row->kind instanceof ExpenseKind ? $row->kind : ExpenseKind::tryFrom((string) $row->kind);
        $category = $row->category instanceof ExpenseCategory ? $row->category : ExpenseCategory::tryFrom((string) $row->category);

        return [
            'id' => $row->id,
            'school_year_id' => $row->school_year_id,
            'kind' => $kind?->value ?? 'expense',
            'label' => $row->label,
            'category' => $category?->value ?? 'other',
            'amount' => $row->amount,
            'spent_on' => $row->spent_on?->toDateString(),
            'vendor' => $row->vendor,
            'notes' => $row->notes,
        ];
    }
}
