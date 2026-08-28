<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Support\GradePacks;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class ApplyGradePacks
{
    /**
     * @param  list<string>  $packs
     * @return array{created: list<string>, skipped: list<string>}
     */
    public function execute(string $schoolId, array $packs): array
    {
        $catalog = GradePacks::catalog();
        $created = [];
        $skipped = [];

        foreach ($packs as $pack) {
            $levels = $catalog[$pack] ?? null;
            if ($levels === null) {
                throw new DomainException('Pack de niveaux inconnu.');
            }

            foreach ($levels as $level) {
                $existing = GradeLevel::query()->where('name', $level['name'])->first();
                if ($existing !== null) {
                    $skipped[] = $level['name'];

                    continue;
                }

                $stage = $level['stage'];
                if (! $stage instanceof GradeStage) {
                    continue;
                }

                $grade = GradeLevel::query()->create([
                    'school_id' => $schoolId,
                    'name' => $level['name'],
                    'stage' => $stage,
                    'sequence' => $level['sequence'],
                ]);

                Auditor::record('grade_level.created', 'grade_level', $grade->id, null, [
                    'pack' => $pack,
                ]);

                $created[] = $level['name'];
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }
}
