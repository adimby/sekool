<?php

namespace App\Domain\Finance\Support;

use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Finance\Models\FeeSchedule;

final class ResolveFeeSchedule
{
    public function forYearAndGrade(string $schoolYearId, ?string $gradeLevelId, bool $activeOnly = true): ?FeeSchedule
    {
        $query = FeeSchedule::query()
            ->with('items')
            ->where('school_year_id', $schoolYearId);

        if ($activeOnly) {
            $query->where('status', FeeScheduleStatus::Active);
        }

        $gradeMatch = null;
        if ($gradeLevelId !== null && $gradeLevelId !== '') {
            $gradeMatch = (clone $query)->where('grade_level_id', $gradeLevelId)->first();
        }

        if ($gradeMatch !== null) {
            return $gradeMatch;
        }

        $schoolWide = (clone $query)->whereNull('grade_level_id')->first();
        if ($schoolWide !== null) {
            return $schoolWide;
        }

        if ($activeOnly || $gradeLevelId === null || $gradeLevelId === '') {
            return null;
        }

        return FeeSchedule::query()
            ->with('items')
            ->where('school_year_id', $schoolYearId)
            ->where(function ($builder) use ($gradeLevelId): void {
                $builder->where('grade_level_id', $gradeLevelId)
                    ->orWhereNull('grade_level_id');
            })
            ->orderByRaw('grade_level_id IS NULL')
            ->orderByRaw("case status when 'active' then 0 when 'pending_validation' then 1 else 2 end")
            ->first();
    }
}
