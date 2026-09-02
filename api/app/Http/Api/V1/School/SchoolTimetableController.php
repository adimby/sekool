<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\RecordTimetableSubstitution;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Academic\Models\TimetableSubstitution;
use App\Domain\Academic\Support\EstablishmentTimetablePayload;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SchoolTimetableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TimetableSlot::query()->with(['classroom.gradeLevel', 'teacher']);

        if (! SchoolGate::isDirection($request)) {
            $ids = SchoolGate::visibleClassrooms($request)->pluck('id');
            $query->whereIn('classroom_id', $ids);
        }

        $slots = $query
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->orderBy('classroom_id')
            ->get();

        $from = now()->toDateString();
        $until = now()->addDays(14)->toDateString();
        $substitutions = TimetableSubstitution::query()
            ->with(['substitute', 'slot'])
            ->whereIn('timetable_slot_id', $slots->pluck('id'))
            ->whereBetween('on_date', [$from, $until])
            ->orderBy('on_date')
            ->get();

        return response()->json([
            'data' => $slots->map(fn (TimetableSlot $slot): array => EstablishmentTimetablePayload::slot($slot))->values(),
            'substitutions' => $substitutions
                ->map(fn (TimetableSubstitution $row): array => EstablishmentTimetablePayload::substitution($row))
                ->values(),
        ]);
    }

    public function substitutions(Request $request, string $school, string $classroom): JsonResponse
    {
        $model = $this->guardView($request, $classroom);

        $from = now()->toDateString();
        $rows = TimetableSubstitution::query()
            ->with(['substitute', 'slot'])
            ->where('classroom_id', $model->id)
            ->where('on_date', '>=', $from)
            ->orderBy('on_date')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (TimetableSubstitution $row): array => EstablishmentTimetablePayload::substitution($row))->values(),
        ]);
    }

    public function storeSubstitution(Request $request, string $school, string $slot, RecordTimetableSubstitution $record): JsonResponse
    {
        $model = TimetableSlot::query()->with('classroom')->find($slot);
        if ($model === null || $model->classroom === null || ! SchoolGate::canViewClassroom($request, $model->classroom)) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if (! SchoolGate::isDirection($request)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'on_date' => ['required', 'date'],
            'substitute_person_id' => ['nullable', 'uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $row = $record->execute($school, $slot, (string) $request->user()->person_id, $data);

        return response()->json(['data' => EstablishmentTimetablePayload::substitution($row)], 201);
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
