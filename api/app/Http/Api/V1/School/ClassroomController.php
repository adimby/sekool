<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\CreateClassroom;
use App\Domain\Academic\Actions\UpdateClassroom;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Support\ClassroomFilePayload;
use App\Domain\Finance\Models\Invoice;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClassroomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $classrooms = SchoolGate::visibleClassrooms($request)->with(['mainTeacher', 'delegate', 'viceDelegate'])->get();

        return response()->json([
            'data' => $classrooms->map(fn (Classroom $classroom): array => ClassroomFilePayload::classroom($classroom))->values(),
        ]);
    }

    public function store(Request $request, CreateClassroom $create): JsonResponse
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'grade_level_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:64'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'main_teacher_person_id' => ['nullable', 'uuid'],
        ]);

        $classroom = $create->execute(
            schoolId: (string) $request->route('school'),
            schoolYearId: $data['school_year_id'],
            gradeLevelId: $data['grade_level_id'],
            name: $data['name'],
            capacity: $data['capacity'] ?? null,
            mainTeacherPersonId: $data['main_teacher_person_id'] ?? null,
        );

        if (($data['main_teacher_person_id'] ?? null) !== null) {
            app(UpdateClassroom::class)->execute(
                (string) $request->route('school'),
                $classroom->id,
                ['main_teacher_person_id' => $data['main_teacher_person_id']],
            );
            $classroom->refresh();
        }

        return response()->json(['data' => ClassroomFilePayload::classroom($classroom)], 201);
    }

    public function show(Request $request, string $school, string $classroom): JsonResponse
    {
        $model = Classroom::query()->find($classroom);
        if ($model === null || ! SchoolGate::canViewClassroom($request, $model)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => ClassroomFilePayload::file($model)]);
    }

    public function update(Request $request, string $school, string $classroom, UpdateClassroom $update): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:64'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'main_teacher_person_id' => ['sometimes', 'nullable', 'uuid'],
            'delegate_person_id' => ['sometimes', 'nullable', 'uuid'],
            'vice_delegate_person_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $model = $update->execute($school, $classroom, $data);

        return response()->json(['data' => ClassroomFilePayload::classroom($model)]);
    }

    public function roster(Request $request, string $school, string $classroom): JsonResponse
    {
        $model = Classroom::query()->find($classroom);
        if ($model === null || ! SchoolGate::canViewClassroom($request, $model)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $file = ClassroomFilePayload::file($model);
        $showFinance = SchoolGate::isFinance($request);
        $invoiceByEnrollment = $showFinance
            ? Invoice::query()
                ->whereIn('enrollment_id', collect($file['students'])->pluck('enrollment_id'))
                ->where('status', '!=', 'cancelled')
                ->get()
                ->keyBy('enrollment_id')
            : collect();

        $students = collect($file['students'])->map(function (array $row) use ($invoiceByEnrollment, $showFinance): array {
            if ($showFinance) {
                $invoice = $invoiceByEnrollment->get($row['enrollment_id']);
                $row['invoice'] = $invoice === null ? null : [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'remaining_amount' => $invoice->remainingAmount(),
                    'status' => $invoice->status->value,
                ];
            }

            return $row;
        })->values();

        return response()->json([
            'data' => [
                'classroom' => $file['classroom'],
                'students' => $students,
            ],
        ]);
    }
}
