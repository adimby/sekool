<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\GradeEntry;
use App\Domain\Enrollment\Models\Enrollment;

final class BulletinPayload
{
    /** @return array<string, mixed> */
    public function forEnrollment(Enrollment $enrollment, ?string $academicTermId = null): array
    {
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

        $subjects = [];
        $totalSum = 0.0;
        $totalWeight = 0.0;
        foreach ($bySubject as $row) {
            $avg = $row['weight'] > 0 ? round($row['weighted_sum'] / $row['weight'], 2) : null;
            if ($avg !== null) {
                $totalSum += $avg * $row['weight'];
                $totalWeight += $row['weight'];
            }
            $subjects[] = [
                'subject_id' => $row['subject_id'],
                'subject' => $row['subject'],
                'average' => $avg,
                'entries' => $row['entries'],
            ];
        }

        return [
            'enrollment_id' => $enrollment->id,
            'student_person_id' => $enrollment->person_id,
            'academic_term_id' => $academicTermId,
            'subjects' => $subjects,
            'overall_average' => $totalWeight > 0 ? round($totalSum / $totalWeight, 2) : null,
        ];
    }
}
