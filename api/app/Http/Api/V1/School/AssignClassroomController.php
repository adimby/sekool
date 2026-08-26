<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\AssignToClassroom;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssignClassroomController extends Controller
{
    public function __invoke(Request $request, string $school, string $enrollment, AssignToClassroom $assign): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'uuid'],
        ]);

        $model = $assign->execute(
            schoolId: $school,
            enrollmentId: $enrollment,
            classroomId: $data['classroom_id'],
        );

        return response()->json([
            'data' => [
                'id' => $model->id,
                'classroom_id' => $model->classroom_id,
                'person_id' => $model->person_id,
                'status' => $model->status->value,
            ],
        ]);
    }
}
