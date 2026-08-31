<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\RecordAttendance;
use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\School\Support\SchoolGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AttendanceController extends Controller
{
    public function index(Request $request, string $school): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'uuid'],
            'date' => ['required', 'date'],
            'session' => ['nullable', 'string'],
        ]);

        $classroom = Classroom::query()->find($data['classroom_id']);
        if ($classroom === null || ! SchoolGate::canViewClassroom($request, $classroom)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $session = AttendanceSession::tryFrom($data['session'] ?? AttendanceSession::FullDay->value)
            ?? AttendanceSession::FullDay;

        $enrollments = Enrollment::query()
            ->with('person')
            ->where('classroom_id', $data['classroom_id'])
            ->where('status', EnrollmentStatus::Active)
            ->get();

        $records = AttendanceRecord::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->where('date', $data['date'])
            ->where('session', $session)
            ->get()
            ->keyBy('enrollment_id');

        return response()->json([
            'data' => $enrollments->map(function (Enrollment $enrollment) use ($records): array {
                $record = $records->get($enrollment->id);

                return [
                    'enrollment_id' => $enrollment->id,
                    'person' => $enrollment->person === null ? null : [
                        'id' => $enrollment->person->id,
                        'public_id' => $enrollment->person->public_id,
                        'first_name' => $enrollment->person->first_name,
                        'last_name' => $enrollment->person->last_name,
                    ],
                    'attendance' => $record === null ? null : [
                        'id' => $record->id,
                        'status' => $record->status->value,
                        'minutes_late' => $record->minutes_late,
                        'reason' => $record->reason,
                        'justification' => $record->justification,
                    ],
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, RecordAttendance $record): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'session' => ['nullable', 'string'],
            'recorded_via' => ['nullable', 'string', 'in:web,offline_sync'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.enrollment_id' => ['required', 'uuid'],
            'records.*.status' => ['required', 'string'],
            'records.*.client_reference' => ['nullable', 'uuid'],
            'records.*.minutes_late' => ['nullable', 'integer', 'min:1'],
            'records.*.reason' => ['nullable', 'string', 'max:255'],
            'records.*.justification' => ['nullable', 'string', 'max:500'],
        ]);

        $session = AttendanceSession::tryFrom($data['session'] ?? AttendanceSession::FullDay->value);
        if ($session === null) {
            return response()->json(['message' => 'Session de présence inconnue.'], 422);
        }

        $schoolId = (string) $request->route('school');
        $actorId = $request->user()->person_id;
        $via = $data['recorded_via'] ?? 'web';

        $enrollmentIds = collect($data['records'])->pluck('enrollment_id')->unique()->all();
        $enrollments = Enrollment::query()->whereIn('id', $enrollmentIds)->get();
        if ($enrollments->count() !== count($enrollmentIds) || $enrollments->contains(fn (Enrollment $row) => $row->classroom_id === null)) {
            return response()->json(['message' => 'Inscription introuvable.'], 404);
        }

        $classrooms = Classroom::query()->whereIn('id', $enrollments->pluck('classroom_id')->unique())->get();
        foreach ($classrooms as $classroom) {
            if (! SchoolGate::canTakeAttendance($request, $classroom)) {
                return response()->json(['message' => 'L’appel se fait par le professeur de la classe.'], 403);
            }
        }

        $saved = DB::transaction(function () use ($data, $session, $record, $schoolId, $actorId, $via) {
            $rows = [];
            foreach ($data['records'] as $row) {
                $status = AttendanceStatus::tryFrom($row['status']);
                if ($status === null) {
                    continue;
                }

                $rows[] = $record->execute(
                    schoolId: $schoolId,
                    enrollmentId: $row['enrollment_id'],
                    date: $data['date'],
                    session: $session,
                    status: $status,
                    recordedByPersonId: $actorId,
                    clientReference: $row['client_reference'] ?? null,
                    recordedVia: $via,
                    minutesLate: $row['minutes_late'] ?? null,
                    reason: $row['reason'] ?? null,
                    justification: $row['justification'] ?? null,
                );
            }

            return $rows;
        });

        return response()->json([
            'data' => collect($saved)->map(fn ($row) => [
                'id' => $row->id,
                'enrollment_id' => $row->enrollment_id,
                'date' => $row->date?->toDateString(),
                'session' => $row->session->value,
                'status' => $row->status->value,
                'reason' => $row->reason,
                'justification' => $row->justification,
                'client_reference' => $row->client_reference,
            ])->values(),
        ], 201);
    }
}
