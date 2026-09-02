<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class RecordAttendance
{
    public function execute(
        string $schoolId,
        string $enrollmentId,
        string $date,
        AttendanceSession $session,
        AttendanceStatus $status,
        string $recordedByPersonId,
        ?string $clientReference = null,
        string $recordedVia = 'web',
        ?int $minutesLate = null,
        ?string $reason = null,
        ?string $justification = null,
        ?string $timetableSlotId = null,
    ): AttendanceRecord {
        $enrollment = Enrollment::query()->find($enrollmentId);
        if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
            throw new DomainException('Inscription introuvable.', 404);
        }

        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw new DomainException('La présence ne peut être saisie que pour une inscription active.');
        }

        if ($timetableSlotId !== null) {
            $session = AttendanceSession::Period;
        }

        if ($clientReference !== null) {
            $existing = AttendanceRecord::query()->where('client_reference', $clientReference)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use (
                $schoolId,
                $enrollment,
                $date,
                $session,
                $status,
                $recordedByPersonId,
                $clientReference,
                $recordedVia,
                $minutesLate,
                $reason,
                $justification,
                $timetableSlotId,
            ): AttendanceRecord {
                $current = $this->lockExisting($enrollment->id, $date, $session, $timetableSlotId);

                $previous = $current?->status;

                if ($current !== null) {
                    $current->fill([
                        'session' => $session,
                        'status' => $status,
                        'minutes_late' => $minutesLate,
                        'reason' => $reason,
                        'justification' => $justification,
                        'recorded_by_person_id' => $recordedByPersonId,
                        'recorded_via' => $recordedVia,
                        'timetable_slot_id' => $timetableSlotId,
                    ]);
                    $current->save();
                    $record = $current;
                } else {
                    $record = AttendanceRecord::query()->create([
                        'school_id' => $schoolId,
                        'enrollment_id' => $enrollment->id,
                        'date' => $date,
                        'session' => $session,
                        'status' => $status,
                        'minutes_late' => $minutesLate,
                        'reason' => $reason,
                        'justification' => $justification,
                        'recorded_by_person_id' => $recordedByPersonId,
                        'recorded_via' => $recordedVia,
                        'client_reference' => $clientReference,
                        'timetable_slot_id' => $timetableSlotId,
                    ]);

                    Auditor::record(
                        'attendance.recorded',
                        'attendance_record',
                        $record->id,
                        $enrollment->person_id,
                        [
                            'date' => $date,
                            'status' => $status->value,
                            'timetable_slot_id' => $timetableSlotId,
                        ],
                    );
                }

                $becameAbsent = $status === AttendanceStatus::Absent
                    && $previous !== AttendanceStatus::Absent
                    && $recordedVia !== 'seed';
                if ($becameAbsent) {
                    app(NotifyAbsenceToFamily::class)->execute($enrollment, $date);
                }

                return $record;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($clientReference !== null) {
                $replay = AttendanceRecord::query()->where('client_reference', $clientReference)->first();
                if ($replay !== null) {
                    return $replay;
                }
            }

            $current = $this->findExisting($enrollment->id, $date, $session, $timetableSlotId);

            if ($current !== null) {
                return $current;
            }

            throw $e;
        }
    }

    private function lockExisting(
        string $enrollmentId,
        string $date,
        AttendanceSession $session,
        ?string $timetableSlotId,
    ): ?AttendanceRecord {
        $query = AttendanceRecord::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('date', $date);

        if ($timetableSlotId !== null) {
            $query->where('timetable_slot_id', $timetableSlotId);
        } else {
            $query->whereNull('timetable_slot_id')->where('session', $session->value);
        }

        return $query->lockForUpdate()->first();
    }

    private function findExisting(
        string $enrollmentId,
        string $date,
        AttendanceSession $session,
        ?string $timetableSlotId,
    ): ?AttendanceRecord {
        $query = AttendanceRecord::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('date', $date);

        if ($timetableSlotId !== null) {
            $query->where('timetable_slot_id', $timetableSlotId);
        } else {
            $query->whereNull('timetable_slot_id')->where('session', $session->value);
        }

        return $query->first();
    }
}
