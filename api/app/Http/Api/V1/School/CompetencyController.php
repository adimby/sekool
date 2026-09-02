<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\EnsureCompetencyCatalog;
use App\Domain\Academic\Actions\RecordCompetencyAssessment;
use App\Domain\Academic\Enums\CompetencyLevel;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\CompetencyAssessment;
use App\Domain\Academic\Models\CompetencyDomain;
use App\Domain\Academic\Support\ClassroomCycle;
use App\Domain\Academic\Support\CompetencyPayload;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CompetencyController extends Controller
{
    public function index(Request $request, string $school, string $classroom, EnsureCompetencyCatalog $catalog): JsonResponse
    {
        $model = $this->guardView($request, $classroom);
        $stage = ClassroomCycle::of($model);

        if (! $stage->usesLivret()) {
            return response()->json(CompetencyPayload::livret([], [], false));
        }

        $catalog->forSchool($school, $stage);

        $domains = CompetencyDomain::query()
            ->with('items')
            ->where('school_id', $school)
            ->where('stage', $stage)
            ->orderBy('sequence')
            ->get();

        $assessments = CompetencyAssessment::query()
            ->where('classroom_id', $model->id)
            ->orderByDesc('assessed_on')
            ->get();

        return response()->json(CompetencyPayload::livret($domains, $assessments));
    }

    public function store(
        Request $request,
        string $school,
        string $classroom,
        EnsureCompetencyCatalog $catalog,
        RecordCompetencyAssessment $record,
    ): JsonResponse {
        $model = $this->guardWrite($request, $classroom);
        $catalog->forSchool($school, ClassroomCycle::of($model));

        $data = $request->validate([
            'enrollment_id' => ['required', 'uuid'],
            'competency_item_id' => ['required', 'uuid'],
            'level' => ['required', 'string', Rule::enum(CompetencyLevel::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
            'assessed_on' => ['required', 'date'],
            'academic_term_id' => ['nullable', 'uuid'],
        ]);

        $assessment = $record->execute(
            $school,
            $classroom,
            (string) $request->user()->person_id,
            $data,
        );

        return response()->json(['data' => CompetencyPayload::assessment($assessment)], 201);
    }

    private function guardView(Request $request, string $classroomId): Classroom
    {
        $model = Classroom::query()->with('gradeLevel')->find($classroomId);
        if ($model === null || ! SchoolGate::canViewClassroom($request, $model)) {
            abort(response()->json(['message' => 'Not found.'], 404));
        }

        return $model;
    }

    private function guardWrite(Request $request, string $classroomId): Classroom
    {
        $model = $this->guardView($request, $classroomId);
        if (! SchoolGate::isDirection($request) && ! SchoolGate::teaches($request, $model)) {
            abort(response()->json(['message' => 'Not found.'], 404));
        }

        return $model;
    }
}
