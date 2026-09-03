<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\PublishClassPost;
use App\Domain\Academic\Enums\ClassPostKind;
use App\Domain\Academic\Models\ClassPost;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Support\ClassLifePayload;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ClassPostController extends Controller
{
    public function index(Request $request, string $school, string $classroom): JsonResponse
    {
        $model = $this->guard($request, $classroom);
        if (! ClassPost::tableReady()) {
            return response()->json(['data' => []]);
        }

        $rows = ClassPost::query()
            ->where('classroom_id', $model->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (ClassPost $row): array => ClassLifePayload::post($row))->values(),
        ]);
    }

    public function store(Request $request, string $school, string $classroom, PublishClassPost $publish): JsonResponse
    {
        $this->guard($request, $classroom);

        $data = $request->validate([
            'kind' => ['required', 'string', Rule::enum(ClassPostKind::class)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:8000'],
            'due_on' => ['nullable', 'date'],
            'held_on' => ['nullable', 'date'],
            'attachment_name' => ['nullable', 'string', 'max:120'],
            'attachment_body' => ['nullable', 'string', 'max:16000'],
        ]);

        $post = $publish->execute(
            $school,
            $classroom,
            (string) $request->user()->person_id,
            $data,
        );

        return response()->json(['data' => ClassLifePayload::post($post)], 201);
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
