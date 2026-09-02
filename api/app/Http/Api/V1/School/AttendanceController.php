<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\RecordAttendance;
use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Academic\Support\ClassroomCycle;
use App\Domain\Academic\Support\TimetableDuty;
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
            'timetable_slot_id' => ['nullable', 'uuid'],
        ]);

        $classroom = Classroom::query()->with('gradeLevel')->find($data['classroom_id']);
        if ($classroom === null || ! SchoolGate::canViewClassroom($request, $classroom)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $session = AttendanceSession::tryFrom($data['session'] ?? AttendanceSession::FullDay->value)
            ?? AttendanceSession::FullDay;
        $slotId = $data['timetable_slot_id'] ?? null;
        if ($slotId !== null) {
            $session = AttendanceSession::Period;
        }

        $enrollments = Enrollment::query()
            ->with('person')
            ->where('classroom_id', $data['classroom_id'])
            ->where('status', EnrollmentStatus::Active)
            ->orderBy('student_number')
            ->orderBy('id')
            ->get();

        $recordsQuery = AttendanceRecord::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->where('date', $data['date']);

        if ($slotId !== null) {
            $recordsQuery->where('timetable_slot_id', $slotId);
        } else {
            $recordsQuery->whereNull('timetable_slot_id')->where('session', $session);
        }

        $records = $recordsQuery->get()->keyBy('enrollment_id');
        $courses = $this->coursesFor($classroom, $data['date']);

        return response()->json([
            'requires_course' => ClassroomCycle::requiresCourseForAttendance($classroom),
            'courses' => $courses,
            'data' => $enrollments->map(function (Enrollment $enrollment) use ($records): array {
                $record = $records->get($enrollment->id);

                return [
                    'enrollment_id' => $enrollment->id,
                    'student_number' => $enrollment->student_number,
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
                        'timetable_slot_id' => $record->timetable_slot_id,
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
            'timetable_slot_id' => ['nullable', 'uuid'],
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
        $slotId = $data['timetable_slot_id'] ?? null;
        $slot = null;

        if ($slotId !== null) {
            $slot = TimetableSlot::query()->find($slotId);
            if ($slot === null) {
                return response()->json(['message' => 'Cours introuvable.'], 404);
            }
            $session = AttendanceSession::Period;
        }

        $enrollmentIds = collect($data['records'])->pluck('enrollment_id')->unique()->all();
        $enrollments = Enrollment::query()->whereIn('id', $enrollmentIds)->get();
        if ($enrollments->count() !== count($enrollmentIds) || $enrollments->contains(fn (Enrollment $row) => $row->classroom_id === null)) {
            return response()->json(['message' => 'Inscription introuvable.'], 404);
        }

        $classrooms = Classroom::query()
            ->with('gradeLevel')
            ->whereIn('id', $enrollments->pluck('classroom_id')->unique())
            ->get();

        foreach ($classrooms as $classroom) {
            if (! SchoolGate::isTeacher($request)) {
                return response()->json(['message' => 'L’appel se fait par le professeur du cours.'], 403);
            }
            if ($slot !== null) {
                if ((string) $slot->classroom_id !== (string) $classroom->id) {
                    return response()->json(['message' => 'Ce cours n’appartient pas à cette classe.'], 422);
                }
                if ((int) $slot->weekday !== TimetableDuty::weekdayOf($data['date'])) {
                    return response()->json(['message' => 'Ce cours n’a pas lieu à cette date.'], 422);
                }
                if (TimetableDuty::isCancelled($slot, $data['date'])) {
                    return response()->json(['message' => 'Ce cours est annulé.'], 403);
                }
                if (! SchoolGate::canTakeAttendance($request, $classroom, $slot, $data['date'])) {
                    return response()->json(['message' => 'L’appel se fait par le professeur du cours.'], 403);
                }
            } else {
                if (ClassroomCycle::requiresCourseForAttendance($classroom)) {
                    return response()->json(['message' => 'Choisissez le cours.'], 422);
                }
                if (! SchoolGate::canTakeAttendance($request, $classroom, null, $data['date'])) {
                    return response()->json(['message' => 'L’appel se fait par le professeur de la classe.'], 403);
                }
            }
        }

        $saved = DB::transaction(function () use ($data, $session, $record, $schoolId, $actorId, $via, $slotId) {
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
                    timetableSlotId: $slotId,
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
                'timetable_slot_id' => $row->timetable_slot_id,
            ])->values(),
        ], 201);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function coursesFor(Classroom $classroom, string $date): array
    {
        $weekday = TimetableDuty::weekdayOf($date);

        return TimetableSlot::query()
            ->where('classroom_id', $classroom->id)
            ->where('weekday', $weekday)
            ->orderBy('starts_at')
            ->get()
            ->map(function (TimetableSlot $slot) use ($date): array {
                $effective = TimetableDuty::effectiveTeacherPersonId($slot, $date);
                $cancelled = $effective === null && TimetableDuty::isCancelled($slot, $date);

                return [
                    'id' => $slot->id,
                    'subject' => $slot->subject,
                    'starts_at' => substr((string) $slot->starts_at, 0, 5),
                    'ends_at' => substr((string) $slot->ends_at, 0, 5),
                    'room' => $slot->room,
                    'teacher_person_id' => $effective,
                    'scheduled_teacher_person_id' => $slot->teacher_person_id,
                    'cancelled' => $cancelled,
                ];
            })
            ->values()
            ->all();
    }
}
