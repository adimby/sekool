<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\CreateClassroom;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Support\FeeSchedulePayload;
use App\Domain\Finance\Support\ResolveFeeSchedule;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClassroomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $classrooms = SchoolGate::visibleClassrooms($request)->with('mainTeacher')->get();

        return response()->json([
            'data' => $classrooms->map(fn (Classroom $classroom): array => $this->serializeClassroom($classroom))->values(),
        ]);
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

        return response()->json(['data' => $this->serializeClassroom($classroom->load('gradeLevel', 'mainTeacher'))], 201);
    }

    public function roster(Request $request, string $school, string $classroom, ResolveFeeSchedule $resolve): JsonResponse
    {
        $model = Classroom::query()->with(['gradeLevel', 'mainTeacher'])->find($classroom);
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

        $schedule = $resolve->forYearAndGrade(
            $model->school_year_id,
            $model->grade_level_id,
            false,
        );

        return response()->json([
            'data' => [
                'classroom' => $this->serializeClassroom($model),
                'fee_schedule' => $schedule === null ? null : FeeSchedulePayload::make($schedule),
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

    /**
     * @return array<string, mixed>
     */
    private function serializeClassroom(Classroom $classroom): array
    {
        $classroom->loadMissing(['gradeLevel', 'mainTeacher']);

        return [
            'id' => $classroom->id,
            'school_id' => $classroom->school_id,
            'school_year_id' => $classroom->school_year_id,
            'grade_level_id' => $classroom->grade_level_id,
            'name' => $classroom->name,
            'capacity' => $classroom->capacity,
            'main_teacher_person_id' => $classroom->main_teacher_person_id,
            'grade_level' => $classroom->gradeLevel === null ? null : [
                'id' => $classroom->gradeLevel->id,
                'name' => $classroom->gradeLevel->name,
            ],
            'main_teacher' => $classroom->mainTeacher === null ? null : [
                'id' => $classroom->mainTeacher->id,
                'first_name' => $classroom->mainTeacher->first_name,
                'last_name' => $classroom->mainTeacher->last_name,
            ],
        ];
    }
}
