<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\CreateClassroom;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Models\Invoice;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClassroomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $classrooms = SchoolGate::visibleClassrooms($request)->get();

        return response()->json(['data' => $classrooms]);
    }

    public function store(Request $request, CreateClassroom $create): JsonResponse
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'grade_level_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:64'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $classroom = $create->execute(
            schoolId: (string) $request->route('school'),
            schoolYearId: $data['school_year_id'],
            gradeLevelId: $data['grade_level_id'],
            name: $data['name'],
            capacity: $data['capacity'] ?? null,
        );

        return response()->json(['data' => $classroom->load('gradeLevel')], 201);
    }

    public function roster(Request $request, string $school, string $classroom): JsonResponse
    {
        $model = Classroom::query()->with('gradeLevel')->find($classroom);
        if ($model === null || ! SchoolGate::canViewClassroom($request, $model)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $enrollments = Enrollment::query()
            ->with('person')
            ->where('classroom_id', $model->id)
            ->where('status', EnrollmentStatus::Active)
            ->orderBy('student_number')
            ->get();

        $showFinance = SchoolGate::isFinance($request);
        $invoiceByEnrollment = $showFinance
            ? Invoice::query()
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->where('status', '!=', 'cancelled')
                ->get()
                ->keyBy('enrollment_id')
            : collect();

        return response()->json([
            'data' => [
                'classroom' => $model,
                'students' => $enrollments->map(function (Enrollment $enrollment) use ($invoiceByEnrollment, $showFinance): array {
                    $row = [
                        'enrollment_id' => $enrollment->id,
                        'person_id' => $enrollment->person_id,
                        'student_number' => $enrollment->student_number,
                        'status' => $enrollment->status->value,
                        'person' => $enrollment->person === null ? null : [
                            'id' => $enrollment->person->id,
                            'public_id' => $enrollment->person->public_id,
                            'first_name' => $enrollment->person->first_name,
                            'last_name' => $enrollment->person->last_name,
                        ],
                    ];

                    if ($showFinance) {
                        $invoice = $invoiceByEnrollment->get($enrollment->id);
                        $row['invoice'] = $invoice === null ? null : [
                            'id' => $invoice->id,
                            'number' => $invoice->number,
                            'remaining_amount' => $invoice->remainingAmount(),
                            'status' => $invoice->status->value,
                        ];
                    }

                    return $row;
                })->values(),
            ],
        ]);
    }
}
