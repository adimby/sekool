<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Enums\StudentAlertCategory;
use App\Domain\Academic\Enums\StudentAlertSeverity;
use App\Domain\Academic\Enums\StudentAlertStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Academic\Models\StudentAlert;
use App\Domain\Academic\Models\StudentAlertSignal;
use App\Domain\Academic\Support\AlertCopy;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class DetectStudentAlerts
{
    public const DETECTOR_VERSION = 'ew-v1';

    /** @return Collection<int, StudentAlert> */
    public function execute(): Collection
    {
        $schoolId = TenantContext::requireSchoolId();
        $opened = collect();
        $windowStart = Carbon::now()->subDays(14)->toDateString();
        $windowEnd = Carbon::now()->toDateString();

        foreach (Enrollment::query()->where('status', EnrollmentStatus::Active)->get() as $enrollment) {
            $absent = AttendanceRecord::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('status', AttendanceStatus::Absent)
                ->where('date', '>=', $windowStart)
                ->count();

            if ($absent < 3) {
                continue;
            }

            $existing = StudentAlert::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('category', StudentAlertCategory::AbsenceIncrease)
                ->where('status', StudentAlertStatus::Open)
                ->first();

            if ($existing !== null) {
                continue;
            }

            $alert = StudentAlert::query()->create([
                'school_id' => $schoolId,
                'enrollment_id' => $enrollment->id,
                'category' => StudentAlertCategory::AbsenceIncrease,
                'severity' => $absent >= 5 ? StudentAlertSeverity::Priority : StudentAlertSeverity::Attention,
                'reason_summary' => AlertCopy::summary(StudentAlertCategory::AbsenceIncrease),
                'detected_at' => now(),
                'detector_version' => self::DETECTOR_VERSION,
                'recommended_action' => AlertCopy::recommendedAction(StudentAlertCategory::AbsenceIncrease),
                'status' => StudentAlertStatus::Open,
            ]);

            StudentAlertSignal::query()->create([
                'school_id' => $schoolId,
                'alert_id' => $alert->id,
                'signal_type' => 'absent_14d',
                'observed_value' => $absent,
                'baseline_value' => 2,
                'window_start' => $windowStart,
                'window_end' => $windowEnd,
                'evidence' => ['count' => $absent],
            ]);

            $opened->push($alert);
        }

        return $opened;
    }
}
