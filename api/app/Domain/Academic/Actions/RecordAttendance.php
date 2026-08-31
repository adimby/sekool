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
    ): AttendanceRecord {
        $enrollment = Enrollment::query()->find($enrollmentId);
        if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
            throw new DomainException('Inscription introuvable.', 404);
        }

        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw new DomainException('La présence ne peut être saisie que pour une inscription active.');
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
            ): AttendanceRecord {
                $current = AttendanceRecord::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('date', $date)
                    ->where('session', $session->value)
                    ->lockForUpdate()
                    ->first();

                $previous = $current?->status;

                if ($current !== null) {
                    $current->fill([
                        'status' => $status,
                        'minutes_late' => $minutesLate,
                        'reason' => $reason,
                        'justification' => $justification,
                        'recorded_by_person_id' => $recordedByPersonId,
                        'recorded_via' => $recordedVia,
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
                    ]);

                    Auditor::record(
                        'attendance.recorded',
                        'attendance_record',
                        $record->id,
                        $enrollment->person_id,
                        ['date' => $date, 'status' => $status->value],
                    );
                }

                $becameAbsent = $status === AttendanceStatus::Absent
                    && $previous !== AttendanceStatus::Absent
                    && $recordedVia !== 'seed';
                if ($becameAbsent) {
                    app(NotifyAbsenceToFamily::class)->execute($enrollment, $date, $session->value);
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

            $current = AttendanceRecord::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('date', $date)
                ->where('session', $session->value)
                ->first();

            if ($current !== null) {
                return $current;
            }

            throw $e;
        }
    }
}
