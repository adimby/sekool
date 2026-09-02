<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\BulletinPayload;
use App\Domain\Academic\Actions\RecordBulletinComment;
use App\Domain\Academic\Actions\RecordGrade;
use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\GradeEntry;
use App\Domain\Academic\Models\Subject;
use App\Domain\Academic\Support\ClassroomCycle;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GradeController extends Controller
{
    public function subjects(): JsonResponse
    {
        $rows = Subject::query()->orderBy('name')->get();

        return response()->json([
            'data' => $rows->map(fn (Subject $row): array => [
                'id' => $row->id,
                'name' => $row->name,
            ])->values(),
        ]);
    }

    public function storeSubject(Request $request, string $school): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $subject = Subject::query()->firstOrCreate(
            ['school_id' => $school, 'name' => trim($data['name'])],
        );

        return response()->json([
            'data' => ['id' => $subject->id, 'name' => $subject->name],
        ], $subject->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request, string $school, string $classroom): JsonResponse
    {
        $model = Classroom::query()->with('gradeLevel')->find($classroom);
        if ($model === null || ! SchoolGate::canViewClassroom($request, $model)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $enabled = ClassroomCycle::of($model) !== GradeStage::Preschool;
        $enrollmentIds = Enrollment::query()
            ->where('classroom_id', $model->id)
            ->where('status', EnrollmentStatus::Active)
            ->pluck('id');

        $entries = $enabled
            ? GradeEntry::query()->with('subject')->whereIn('enrollment_id', $enrollmentIds)->orderByDesc('assessed_on')->get()
            : collect();

        return response()->json([
            'grades_enabled' => $enabled,
            'data' => $entries->map(fn (GradeEntry $row): array => $this->serialize($row))->values(),
        ]);
    }

    public function store(Request $request, string $school, string $classroom, RecordGrade $record): JsonResponse
    {
        $model = Classroom::query()->with('gradeLevel')->find($classroom);
        if ($model === null || ! SchoolGate::canViewClassroom($request, $model)) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if (! SchoolGate::isDirection($request) && ! SchoolGate::teaches($request, $model)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'enrollment_id' => ['required', 'uuid'],
            'subject_id' => ['required', 'uuid'],
            'academic_term_id' => ['nullable', 'uuid'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_value' => ['nullable', 'numeric', 'min:1'],
            'coefficient' => ['nullable', 'numeric', 'min:0.01'],
            'assessed_on' => ['required', 'date'],
        ]);

        $enrollment = Enrollment::query()->find($data['enrollment_id']);
        if ($enrollment === null || (string) $enrollment->classroom_id !== (string) $model->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $entry = $record->execute($school, (string) $request->user()->person_id, $data);

        return response()->json(['data' => $this->serialize($entry)], 201);
    }

    public function bulletin(Request $request, string $school, string $enrollment, BulletinPayload $bulletin): JsonResponse
    {
        $row = Enrollment::query()->with('classroom.gradeLevel')->find($enrollment);
        if ($row === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if ($row->classroom !== null && ! SchoolGate::canViewClassroom($request, $row->classroom)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $termId = $request->query('academic_term_id');
        $termId = is_string($termId) && $termId !== '' ? $termId : null;

        return response()->json(['data' => $bulletin->forEnrollment($row, $termId)]);
    }

    public function storeComment(Request $request, string $school, string $enrollment, RecordBulletinComment $record): JsonResponse
    {
        $row = Enrollment::query()->with('classroom.gradeLevel')->find($enrollment);
        if ($row === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if ($row->classroom === null || ! SchoolGate::canViewClassroom($request, $row->classroom)) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if (! SchoolGate::isDirection($request) && ! SchoolGate::teaches($request, $row->classroom)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'subject_id' => ['nullable', 'uuid'],
            'academic_term_id' => ['nullable', 'uuid'],
        ]);

        $comment = $record->execute($school, $enrollment, (string) $request->user()->person_id, $data);

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'enrollment_id' => $comment->enrollment_id,
                'subject_id' => $comment->subject_id,
                'body' => $comment->body,
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(GradeEntry $row): array
    {
        return [
            'id' => $row->id,
            'enrollment_id' => $row->enrollment_id,
            'subject_id' => $row->subject_id,
            'subject' => $row->subject?->name,
            'academic_term_id' => $row->academic_term_id,
            'value' => (float) $row->value,
            'max_value' => (float) $row->max_value,
            'coefficient' => (float) $row->coefficient,
            'assessed_on' => $row->assessed_on?->toDateString(),
        ];
    }
}
