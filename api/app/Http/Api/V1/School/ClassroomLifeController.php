<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\AddClassroomTeacher;
use App\Domain\Academic\Actions\RemoveClassroomTeacher;
use App\Domain\Academic\Actions\SaveClassActivity;
use App\Domain\Academic\Actions\SaveClassCouncil;
use App\Domain\Academic\Actions\SaveTimetableSlot;
use App\Domain\Academic\Models\ClassActivity;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Academic\Support\ClassroomFilePayload;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClassroomLifeController extends Controller
{
    public function addTeacher(Request $request, string $school, string $classroom, AddClassroomTeacher $add): JsonResponse
    {
        $this->guard($request, $classroom);
        $data = $request->validate([
            'person_id' => ['required', 'uuid'],
            'subject' => ['nullable', 'string', 'max:64'],
        ]);

        $add->execute($school, $classroom, $data['person_id'], $data['subject'] ?? null);

        return response()->json(['data' => $this->file($classroom)], 201);
    }

    public function removeTeacher(Request $request, string $school, string $classroom, string $person, RemoveClassroomTeacher $remove): JsonResponse
    {
        $this->guard($request, $classroom);
        $remove->execute($school, $classroom, $person);

        return response()->json(['data' => $this->file($classroom)]);
    }

    public function storeSlot(Request $request, string $school, string $classroom, SaveTimetableSlot $save): JsonResponse
    {
        $this->guard($request, $classroom);
        $slot = $save->execute($school, $classroom, $this->slotData($request));

        return response()->json(['data' => $this->file($classroom)], 201);
    }

    public function updateSlot(Request $request, string $school, string $classroom, string $slot, SaveTimetableSlot $save): JsonResponse
    {
        $this->guard($request, $classroom);
        $save->execute($school, $classroom, $this->slotData($request), $slot);

        return response()->json(['data' => $this->file($classroom)]);
    }

    public function destroySlot(Request $request, string $school, string $classroom, string $slot): JsonResponse
    {
        $this->guard($request, $classroom);
        $model = TimetableSlot::query()->where('classroom_id', $classroom)->find($slot);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $model->delete();

        return response()->json(['data' => $this->file($classroom)]);
    }

    public function storeCouncil(Request $request, string $school, string $classroom, SaveClassCouncil $save): JsonResponse
    {
        $this->guard($request, $classroom);
        $save->execute($school, $classroom, $this->councilData($request));

        return response()->json(['data' => $this->file($classroom)], 201);
    }

    public function updateCouncil(Request $request, string $school, string $classroom, string $council, SaveClassCouncil $save): JsonResponse
    {
        $this->guard($request, $classroom);
        $save->execute($school, $classroom, $this->councilData($request), $council);

        return response()->json(['data' => $this->file($classroom)]);
    }

    public function storeActivity(Request $request, string $school, string $classroom, SaveClassActivity $save): JsonResponse
    {
        $this->guard($request, $classroom);
        $save->execute($school, $classroom, $this->activityData($request));

        return response()->json(['data' => $this->file($classroom)], 201);
    }

    public function updateActivity(Request $request, string $school, string $classroom, string $activity, SaveClassActivity $save): JsonResponse
    {
        $this->guard($request, $classroom);
        $save->execute($school, $classroom, $this->activityData($request), $activity);

        return response()->json(['data' => $this->file($classroom)]);
    }

    public function destroyActivity(Request $request, string $school, string $classroom, string $activity): JsonResponse
    {
        $this->guard($request, $classroom);
        $model = ClassActivity::query()->where('classroom_id', $classroom)->find($activity);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $model->delete();

        return response()->json(['data' => $this->file($classroom)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function slotData(Request $request): array
    {
        return $request->validate([
            'weekday' => ['required', 'integer', 'min:1', 'max:6'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'subject' => ['required', 'string', 'max:64'],
            'teacher_person_id' => ['nullable', 'uuid'],
            'room' => ['nullable', 'string', 'max:32'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function councilData(Request $request): array
    {
        return $request->validate([
            'academic_term_id' => ['nullable', 'uuid'],
            'held_on' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'minutes' => ['nullable', 'string', 'max:8000'],
            'status' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function activityData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string'],
            'title' => ['required', 'string', 'max:120'],
            'held_on' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function guard(Request $request, string $classroomId): Classroom
    {
        $model = Classroom::query()->find($classroomId);
        if ($model === null || ! SchoolGate::canViewClassroom($request, $model)) {
            abort(response()->json(['message' => 'Not found.'], 404));
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function file(string $classroomId): array
    {
        $model = Classroom::query()->findOrFail($classroomId);

        return ClassroomFilePayload::file($model);
    }
}
