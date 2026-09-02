<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\RecordExamSession;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\ExamSession;
use App\Domain\Academic\Support\EstablishmentTimetablePayload;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExamSessionController extends Controller
{
    public function index(Request $request, string $school, string $classroom): JsonResponse
    {
        $model = $this->guardView($request, $classroom);

        $rows = ExamSession::query()
            ->where('classroom_id', $model->id)
            ->where('held_on', '>=', now()->toDateString())
            ->orderBy('held_on')
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (ExamSession $row): array => EstablishmentTimetablePayload::exam($row))->values(),
        ]);
    }

    public function store(Request $request, string $school, string $classroom, RecordExamSession $record): JsonResponse
    {
        $model = $this->guardView($request, $classroom);
        if (! SchoolGate::isDirection($request)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:64'],
            'held_on' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'room' => ['nullable', 'string', 'max:32'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $exam = $record->execute($school, $model->id, (string) $request->user()->person_id, $data);

        return response()->json(['data' => EstablishmentTimetablePayload::exam($exam)], 201);
    }

    private function guardView(Request $request, string $classroomId): Classroom
    {
        $model = Classroom::query()->find($classroomId);
        if ($model === null || ! SchoolGate::canViewClassroom($request, $model)) {
            abort(response()->json(['message' => 'Not found.'], 404));
        }

        return $model;
    }
}
