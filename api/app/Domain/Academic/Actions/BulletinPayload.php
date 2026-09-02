<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Academic\Models\BulletinComment;
use App\Domain\Academic\Models\GradeEntry;
use App\Domain\Enrollment\Models\Enrollment;
use Illuminate\Support\Carbon;

final class BulletinPayload
{
    public const DISCLAIMER = 'Ce relevé est un document FANABE. Ce n’est pas un LSU.';

    /** @return array<string, mixed> */
    public function forEnrollment(Enrollment $enrollment, ?string $academicTermId = null): array
    {
        $enrollment->loadMissing('classroom.gradeLevel');

        $query = GradeEntry::query()
            ->where('enrollment_id', $enrollment->id)
            ->with('subject');

        if ($academicTermId !== null) {
            $query->where('academic_term_id', $academicTermId);
        }

        $entries = $query->orderBy('assessed_on')->get();
        $bySubject = [];

        foreach ($entries as $entry) {
            $key = (string) $entry->subject_id;
            $bySubject[$key] ??= [
                'subject_id' => $entry->subject_id,
                'subject' => $entry->subject?->name,
                'weighted_sum' => 0.0,
                'weight' => 0.0,
                'entries' => [],
            ];
            $weight = max(0.01, (float) $entry->coefficient);
            $bySubject[$key]['weighted_sum'] += ((float) $entry->value) * $weight;
            $bySubject[$key]['weight'] += $weight;
            $bySubject[$key]['entries'][] = [
                'id' => $entry->id,
                'value' => (float) $entry->value,
                'max_value' => (float) $entry->max_value,
                'coefficient' => (float) $entry->coefficient,
                'assessed_on' => $entry->assessed_on?->toDateString(),
            ];
        }

        $comments = BulletinComment::query()
            ->where('enrollment_id', $enrollment->id)
            ->get();
        $overallComment = $comments->first(fn (BulletinComment $row): bool => $row->subject_id === null);
        $commentsBySubject = $comments
            ->filter(fn (BulletinComment $row): bool => $row->subject_id !== null)
            ->keyBy(fn (BulletinComment $row): string => (string) $row->subject_id);

        $subjects = [];
        $totalSum = 0.0;
        $totalWeight = 0.0;
        foreach ($bySubject as $row) {
            $avg = $row['weight'] > 0 ? round($row['weighted_sum'] / $row['weight'], 2) : null;
            if ($avg !== null) {
                $totalSum += $avg * $row['weight'];
                $totalWeight += $row['weight'];
            }
            $subjectId = (string) $row['subject_id'];
            $subjects[] = [
                'subject_id' => $row['subject_id'],
                'subject' => $row['subject'],
                'average' => $avg,
                'comment' => $commentsBySubject->get($subjectId)?->body,
                'entries' => $row['entries'],
            ];
        }

        $windowStart = Carbon::now()->subDays(30)->toDateString();
        $absent = AttendanceRecord::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('date', '>=', $windowStart)
            ->where('status', AttendanceStatus::Absent)
            ->count();
        $late = AttendanceRecord::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('date', '>=', $windowStart)
            ->where('status', AttendanceStatus::Late)
            ->count();

        $stage = $enrollment->classroom !== null
            ? $enrollment->classroom->gradeLevel?->stage
            : null;
        $stageValue = $stage instanceof GradeStage ? $stage->value : (is_string($stage) ? $stage : null);

        return [
            'enrollment_id' => $enrollment->id,
            'student_person_id' => $enrollment->person_id,
            'academic_term_id' => $academicTermId,
            'classroom' => $enrollment->classroom?->name,
            'grade_level' => $enrollment->classroom?->gradeLevel?->name,
            'stage' => $stageValue,
            'disclaimer' => self::DISCLAIMER,
            'absences' => $absent,
            'late' => $late,
            'overall_comment' => $overallComment?->body,
            'comments' => $comments->map(fn (BulletinComment $row): array => [
                'id' => $row->id,
                'subject_id' => $row->subject_id,
                'body' => $row->body,
            ])->values()->all(),
            'subjects' => $subjects,
            'overall_average' => $totalWeight > 0 ? round($totalSum / $totalWeight, 2) : null,
        ];
    }
}
