<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\RecordDisciplinaryCase;
use App\Domain\Academic\Enums\DisciplinaryMeasureType;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\DisciplinaryCase;
use App\Domain\Academic\Support\ClassLifePayload;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DisciplinaryCaseController extends Controller
{
    public function index(Request $request, string $school, string $classroom): JsonResponse
    {
        $model = $this->guard($request, $classroom);

        $rows = DisciplinaryCase::query()
            ->with('enrollment.person')
            ->where('classroom_id', $model->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (DisciplinaryCase $row): array => ClassLifePayload::disciplinaryCase($row))->values(),
        ]);
    }

    public function store(Request $request, string $school, string $classroom, RecordDisciplinaryCase $record): JsonResponse
    {
        $this->guard($request, $classroom);

        $data = $request->validate([
            'enrollment_id' => ['required', 'uuid'],
            'occurred_on' => ['required', 'date'],
            'fact' => ['required', 'string', 'max:2000'],
            'measure_type' => ['required', 'string', Rule::enum(DisciplinaryMeasureType::class)],
            'measure_label' => ['nullable', 'string', 'max:120'],
            'measure_on' => ['nullable', 'date'],
            'follow_up' => ['nullable', 'string', 'max:2000'],
        ]);

        $case = $record->execute(
            $school,
            $classroom,
            (string) $request->user()->person_id,
            $data,
        );

        return response()->json(['data' => ClassLifePayload::disciplinaryCase($case)], 201);
    }

    private function guard(Request $request, string $classroomId): Classroom
    {
        $model = Classroom::query()->find($classroomId);
        if ($model === null || ! SchoolGate::canViewClassroom($request, $model)) {
            abort(response()->json(['message' => 'Not found.'], 404));
        }

        return $model;
    }
}
