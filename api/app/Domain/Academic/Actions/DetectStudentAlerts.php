<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Enums\ClassPostKind;
use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Enums\StudentAlertCategory;
use App\Domain\Academic\Enums\StudentAlertSeverity;
use App\Domain\Academic\Enums\StudentAlertStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Academic\Models\ClassPost;
use App\Domain\Academic\Models\GradeEntry;
use App\Domain\Academic\Models\StudentAlert;
use App\Domain\Academic\Models\StudentAlertSignal;
use App\Domain\Academic\Support\AlertCopy;
use App\Domain\Academic\Support\ClassroomCycle;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class DetectStudentAlerts
{
    public const DETECTOR_VERSION = 'ew-v2';

    /** @return Collection<int, StudentAlert> */
    public function execute(): Collection
    {
        $schoolId = TenantContext::requireSchoolId();
        $opened = collect();
        $windowStart = Carbon::now()->subDays(14)->toDateString();
        $windowEnd = Carbon::now()->toDateString();

        foreach (Enrollment::query()->with('classroom.gradeLevel')->where('status', EnrollmentStatus::Active)->get() as $enrollment) {
            $absent = AttendanceRecord::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('status', AttendanceStatus::Absent)
                ->where('date', '>=', $windowStart)
                ->distinct()
                ->count('date');
            $gradesDrop = $this->gradesDrop($enrollment);
            $homeworkCount = $this->homeworkCount($enrollment, $windowStart);
            $gradesSignal = $gradesDrop !== null;
            $homeworkSignal = $homeworkCount >= 2;

            $category = match (true) {
                $absent >= 3 && ($gradesSignal || $homeworkSignal) => StudentAlertCategory::Combined,
                $gradesSignal && $homeworkSignal => StudentAlertCategory::HomeworkDecline,
                $gradesSignal => StudentAlertCategory::GradesDecline,
                $absent >= 3 => StudentAlertCategory::AbsenceIncrease,
                default => null,
            };

            if ($category === null || $this->hasActiveAlert($enrollment->id, $category)) {
                continue;
            }

            $alert = StudentAlert::query()->create([
                'school_id' => $schoolId,
                'enrollment_id' => $enrollment->id,
                'category' => $category,
                'severity' => $absent >= 5 ? StudentAlertSeverity::Priority : StudentAlertSeverity::Attention,
                'reason_summary' => AlertCopy::summary($category),
                'detected_at' => now(),
                'detector_version' => self::DETECTOR_VERSION,
                'recommended_action' => AlertCopy::recommendedAction($category),
                'status' => StudentAlertStatus::Open,
            ]);

            if ($absent >= 3) {
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
            }

            if ($gradesDrop !== null) {
                StudentAlertSignal::query()->create([
                    'school_id' => $schoolId,
                    'alert_id' => $alert->id,
                    'signal_type' => 'grades_drop',
                    'observed_value' => $gradesDrop['drop'],
                    'baseline_value' => 4,
                    'window_start' => $windowStart,
                    'window_end' => $windowEnd,
                    'evidence' => $gradesDrop,
                ]);
            }

            if ($homeworkSignal) {
                StudentAlertSignal::query()->create([
                    'school_id' => $schoolId,
                    'alert_id' => $alert->id,
                    'signal_type' => 'homework_14d',
                    'observed_value' => $homeworkCount,
                    'baseline_value' => 2,
                    'window_start' => $windowStart,
                    'window_end' => $windowEnd,
                    'evidence' => ['count' => $homeworkCount],
                ]);
            }

            $opened->push($alert);
        }

        return $opened;
    }

    /**
     * @return array{subject_id: string, previous: float, latest: float, drop: float}|null
     */
    private function gradesDrop(Enrollment $enrollment): ?array
    {
        if ($enrollment->classroom !== null && ClassroomCycle::of($enrollment->classroom) === GradeStage::Preschool) {
            return null;
        }

        $grouped = GradeEntry::query()
            ->where('enrollment_id', $enrollment->id)
            ->orderBy('assessed_on')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (GradeEntry $row): string => (string) $row->subject_id);

        foreach ($grouped as $subjectId => $rows) {
            $ordered = $rows->values();
            if ($ordered->count() < 2) {
                continue;
            }

            $previous = $ordered->get($ordered->count() - 2);
            $latest = $ordered->last();
            if ($previous === null || $latest === null) {
                continue;
            }
            $previousValue = $this->normalized($previous);
            $latestValue = $this->normalized($latest);
            $drop = round($previousValue - $latestValue, 2);

            if ($drop >= 4) {
                return [
                    'subject_id' => (string) $subjectId,
                    'previous' => $previousValue,
                    'latest' => $latestValue,
                    'drop' => $drop,
                ];
            }
        }

        return null;
    }

    private function normalized(GradeEntry $entry): float
    {
        $max = max(0.01, (float) $entry->max_value);

        return round(((float) $entry->value / $max) * 20, 2);
    }

    private function homeworkCount(Enrollment $enrollment, string $windowStart): int
    {
        if ($enrollment->classroom_id === null) {
            return 0;
        }

        return ClassPost::query()
            ->where('classroom_id', $enrollment->classroom_id)
            ->where('kind', ClassPostKind::Homework)
            ->where(function ($query) use ($windowStart): void {
                $query->whereDate('created_at', '>=', $windowStart)
                    ->orWhere('due_on', '>=', $windowStart);
            })
            ->count();
    }

    private function hasActiveAlert(string $enrollmentId, StudentAlertCategory $category): bool
    {
        return StudentAlert::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('category', $category)
            ->whereIn('status', [
                StudentAlertStatus::Open,
                StudentAlertStatus::Acknowledged,
                StudentAlertStatus::InProgress,
            ])
            ->exists();
    }
}
