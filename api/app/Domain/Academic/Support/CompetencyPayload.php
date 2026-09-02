<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Enums\CompetencyLevel;
use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\CompetencyAssessment;
use App\Domain\Academic\Models\CompetencyDomain;

final class CompetencyPayload
{
    /**
     * @param  iterable<int, CompetencyDomain>  $domains
     * @param  iterable<int, CompetencyAssessment>  $assessments
     * @return array<string, mixed>
     */
    public static function livret(iterable $domains, iterable $assessments, bool $enabled = true): array
    {
        return [
            'competencies_enabled' => $enabled,
            'levels' => self::levels(),
            'domains' => collect($domains)->map(fn (CompetencyDomain $domain): array => self::domain($domain))->values()->all(),
            'assessments' => collect($assessments)->map(fn (CompetencyAssessment $row): array => self::assessment($row))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function domain(CompetencyDomain $domain): array
    {
        $stage = $domain->stage instanceof GradeStage ? $domain->stage : GradeStage::tryFrom((string) $domain->stage);

        return [
            'id' => $domain->id,
            'stage' => $stage?->value,
            'code' => $domain->code,
            'label' => $domain->label,
            'sequence' => $domain->sequence,
            'items' => $domain->items->map(fn ($item): array => [
                'id' => $item->id,
                'label' => $item->label,
                'sequence' => $item->sequence,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function assessment(CompetencyAssessment $row): array
    {
        $level = $row->level instanceof CompetencyLevel
            ? $row->level
            : CompetencyLevel::tryFrom((string) $row->level);

        return [
            'id' => $row->id,
            'enrollment_id' => $row->enrollment_id,
            'classroom_id' => $row->classroom_id,
            'competency_item_id' => $row->competency_item_id,
            'level' => $level?->value ?? 'not_yet',
            'level_label' => $level?->label() ?? 'Pas encore',
            'comment' => $row->comment,
            'assessed_on' => $row->assessed_on?->toDateString(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function levels(): array
    {
        return array_map(
            fn (CompetencyLevel $level): array => [
                'value' => $level->value,
                'label' => $level->label(),
            ],
            CompetencyLevel::cases(),
        );
    }
}
