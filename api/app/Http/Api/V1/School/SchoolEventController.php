<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\PublishSchoolEvent;
use App\Domain\Academic\Enums\SchoolEventAudience;
use App\Domain\Academic\Enums\SchoolEventType;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\SchoolEvent;
use App\Domain\Academic\Support\ClassLifePayload;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SchoolEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['nullable', 'uuid'],
        ]);

        $query = SchoolEvent::query()->orderByDesc('starts_on')->orderByDesc('created_at');

        if (isset($data['classroom_id'])) {
            $classroom = Classroom::query()->find($data['classroom_id']);
            if ($classroom === null || ! SchoolGate::canViewClassroom($request, $classroom)) {
                return response()->json(['message' => 'Not found.'], 404);
            }
            $this->matchingClassroom($query, $classroom);
        } elseif (! SchoolGate::isDirection($request)) {
            $visible = SchoolGate::visibleClassrooms($request)->get();
            $query->where(function (Builder $inner) use ($visible): void {
                $inner->where('audience', SchoolEventAudience::School->value);
                $gradeIds = $visible->pluck('grade_level_id')->filter()->unique()->all();
                if ($gradeIds !== []) {
                    $inner->orWhere(function (Builder $gradeQuery) use ($gradeIds): void {
                        $gradeQuery->where('audience', SchoolEventAudience::Grade->value)
                            ->whereIn('grade_level_id', $gradeIds);
                    });
                }
                $classroomIds = $visible->pluck('id')->all();
                if ($classroomIds !== []) {
                    $inner->orWhere(function (Builder $classQuery) use ($classroomIds): void {
                        $classQuery->where('audience', SchoolEventAudience::Classroom->value)
                            ->whereIn('classroom_id', $classroomIds);
                    });
                }
            });
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows->map(fn (SchoolEvent $row): array => ClassLifePayload::event($row))->values(),
        ]);
    }

    public function store(Request $request, string $school, PublishSchoolEvent $publish): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::enum(SchoolEventType::class)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:4000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date'],
            'audience' => ['required', 'string', Rule::enum(SchoolEventAudience::class)],
            'grade_level_id' => ['nullable', 'uuid'],
            'classroom_id' => ['nullable', 'uuid'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        $event = $publish->execute($school, (string) $request->user()->person_id, $data);

        return response()->json(['data' => ClassLifePayload::event($event)], 201);
    }

    /**
     * @param  Builder<SchoolEvent>  $query
     */
    private function matchingClassroom(Builder $query, Classroom $classroom): void
    {
        $query->where(function (Builder $inner) use ($classroom): void {
            $inner->where('audience', SchoolEventAudience::School->value)
                ->orWhere(function (Builder $gradeQuery) use ($classroom): void {
                    $gradeQuery->where('audience', SchoolEventAudience::Grade->value)
                        ->where('grade_level_id', $classroom->grade_level_id);
                })
                ->orWhere(function (Builder $classQuery) use ($classroom): void {
                    $classQuery->where('audience', SchoolEventAudience::Classroom->value)
                        ->where('classroom_id', $classroom->id);
                });
        });
    }
}
