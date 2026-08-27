<?php

namespace App\Http\Api\V1\School;

use App\Domain\Finance\Actions\AdjustFeeSchedule;
use App\Domain\Finance\Actions\ConfirmFeeSchedule;
use App\Domain\Finance\Actions\CopyFeeSchedulesFromYear;
use App\Domain\Finance\Actions\CreateFeeSchedule;
use App\Domain\Finance\Actions\ReopenFeeSchedule;
use App\Domain\Finance\Actions\RequestFeeScheduleUnlock;
use App\Domain\Finance\Actions\SubmitFeeSchedule;
use App\Domain\Finance\Actions\UpdateFeeSchedule;
use App\Domain\Finance\Enums\FeeAdjustmentType;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Finance\Support\FeeAmountAdjuster;
use App\Domain\Finance\Support\FeeSchedulePayload;
use App\Domain\Platform\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FeeScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $yearId = $request->query('school_year_id');

        $schedules = FeeSchedule::query()
            ->with(['items', 'gradeLevel', 'schoolYear'])
            ->when(is_string($yearId) && $yearId !== '', fn ($query) => $query->where('school_year_id', $yearId))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $schedules->map(fn (FeeSchedule $schedule): array => FeeSchedulePayload::make($schedule))->values(),
        ]);
    }

    public function show(string $school, string $schedule): JsonResponse
    {
        $model = FeeSchedule::query()->with(['items', 'gradeLevel', 'schoolYear'])->find($schedule);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => FeeSchedulePayload::make($model)]);
    }

    public function store(Request $request, CreateFeeSchedule $create): JsonResponse
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'grade_level_id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.code' => ['nullable', 'string', 'max:32'],
            'items.*.label' => ['required', 'string', 'max:120'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
            'items.*.due_on' => ['required', 'date'],
            'items.*.category' => ['nullable', 'string', 'max:32'],
            'items.*.is_recurring' => ['sometimes', 'boolean'],
        ]);

        $schedule = $create->execute(
            schoolId: (string) $request->route('school'),
            schoolYearId: $data['school_year_id'],
            gradeLevelId: $data['grade_level_id'] ?? null,
            name: $data['name'],
            items: $data['items'],
        );

        return response()->json(['data' => FeeSchedulePayload::make($schedule)], 201);
    }

    public function copyYear(Request $request, CopyFeeSchedulesFromYear $copy): JsonResponse
    {
        $data = $request->validate([
            'source_year_id' => ['required', 'uuid'],
            'target_year_id' => ['required', 'uuid'],
            'adjustment_type' => ['nullable', 'string'],
            'adjustment_amount' => ['nullable', 'integer'],
            'adjustment_percent' => ['nullable', 'numeric'],
        ]);

        [$type, $amount, $bps] = $this->adjustmentFrom($data);

        $schedules = $copy->execute(
            schoolId: (string) $request->route('school'),
            sourceYearId: $data['source_year_id'],
            targetYearId: $data['target_year_id'],
            adjustmentType: $type,
            adjustmentAmount: $amount,
            adjustmentPercentBps: $bps,
        );

        return response()->json([
            'data' => array_map(fn ($schedule) => FeeSchedulePayload::make($schedule), $schedules),
        ], 201);
    }

    public function update(Request $request, string $school, string $schedule, UpdateFeeSchedule $update): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'grade_level_id' => ['sometimes', 'nullable', 'uuid'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.code' => ['nullable', 'string', 'max:32'],
            'items.*.label' => ['required_with:items', 'string', 'max:120'],
            'items.*.amount' => ['required_with:items', 'integer', 'min:1'],
            'items.*.due_on' => ['required_with:items', 'date'],
            'items.*.category' => ['nullable', 'string', 'max:32'],
            'items.*.is_recurring' => ['sometimes', 'boolean'],
        ]);

        $model = $update->execute($school, $schedule, $data);

        return response()->json(['data' => FeeSchedulePayload::make($model)]);
    }

    public function adjust(Request $request, string $school, string $schedule, AdjustFeeSchedule $adjust): JsonResponse
    {
        $data = $request->validate([
            'adjustment_type' => ['required', 'string'],
            'adjustment_amount' => ['nullable', 'integer'],
            'adjustment_percent' => ['nullable', 'numeric'],
        ]);

        [$type, $amount, $bps] = $this->adjustmentFrom($data);
        if ($type === null) {
            return response()->json(['message' => 'Indiquez un ajustement en Ariary ou en pourcentage.'], 422);
        }

        $model = $adjust->execute($school, $schedule, $type, $amount, $bps);

        return response()->json(['data' => FeeSchedulePayload::make($model)]);
    }

    public function submit(Request $request, string $school, string $schedule, SubmitFeeSchedule $submit): JsonResponse
    {
        $model = $submit->execute($school, $schedule, (string) $request->user()->person_id);

        return response()->json(['data' => FeeSchedulePayload::make($model)]);
    }

    public function confirm(Request $request, string $school, string $schedule, ConfirmFeeSchedule $confirm): JsonResponse
    {
        $model = $confirm->execute($school, $schedule, (string) $request->user()->person_id);

        return response()->json(['data' => FeeSchedulePayload::make($model)]);
    }

    public function reopen(Request $request, string $school, string $schedule, ReopenFeeSchedule $reopen): JsonResponse
    {
        $model = $reopen->execute($school, $schedule, (string) $request->user()->person_id);

        return response()->json(['data' => FeeSchedulePayload::make($model)]);
    }

    public function requestUnlock(Request $request, string $school, string $schedule, RequestFeeScheduleUnlock $requestUnlock): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $model = $requestUnlock->execute($school, $schedule, (string) $request->user()->person_id, $data['reason']);

        return response()->json(['data' => FeeSchedulePayload::make($model)]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?FeeAdjustmentType, 1: int, 2: int}
     */
    private function adjustmentFrom(array $data): array
    {
        $raw = $data['adjustment_type'] ?? null;
        if (! is_string($raw) || $raw === '' || $raw === 'none') {
            return [null, 0, 0];
        }

        $type = FeeAdjustmentType::tryFrom($raw);
        if ($type === null) {
            throw new DomainException('Type d’ajustement inconnu.');
        }

        $amount = (int) ($data['adjustment_amount'] ?? 0);
        $bps = isset($data['adjustment_percent'])
            ? FeeAmountAdjuster::percentToBps($data['adjustment_percent'])
            : 0;

        return [$type, $amount, $bps];
    }
}
