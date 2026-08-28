<?php

namespace App\Domain\SchoolKit\Actions;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\SchoolYear;
use App\Domain\SchoolKit\Enums\KitPackTier;
use App\Domain\SchoolKit\Models\KitDefinition;
use App\Domain\SchoolKit\Models\KitNeed;
use App\Domain\SchoolKit\Models\KitPack;
use App\Domain\SchoolKit\Models\Supplier;
use Illuminate\Support\Facades\DB;

final class SaveKitCatalog
{
    /**
     * @param  array{
     *     school_year_id: string,
     *     grade_level_id?: string|null,
     *     name: string,
     *     needs?: list<array{label: string, quantity?: int}>,
     *     supplier_name: string,
     *     supplier_contact?: string|null,
     *     commission_rate_bps?: int,
     *     packs: list<array{tier: string, total_amount: int}>
     * }  $data
     */
    public function execute(string $schoolId, array $data): KitDefinition
    {
        return DB::transaction(function () use ($schoolId, $data): KitDefinition {
            $year = SchoolYear::query()->find($data['school_year_id']);
            if ($year === null || (string) $year->school_id !== $schoolId) {
                throw new DomainException('Année scolaire introuvable.', 404);
            }

            $gradeId = $data['grade_level_id'] ?? null;
            if (is_string($gradeId) && $gradeId !== '') {
                $grade = GradeLevel::query()->find($gradeId);
                if ($grade === null || (string) $grade->school_id !== $schoolId) {
                    throw new DomainException('Niveau introuvable.', 404);
                }
            } else {
                $gradeId = null;
            }

            $definition = KitDefinition::query()->create([
                'school_id' => $schoolId,
                'school_year_id' => $year->id,
                'grade_level_id' => $gradeId,
                'name' => trim($data['name']),
                'status' => 'active',
            ]);

            foreach ($data['needs'] ?? [] as $need) {
                KitNeed::query()->create([
                    'school_id' => $schoolId,
                    'kit_definition_id' => $definition->id,
                    'label' => trim($need['label']),
                    'quantity' => (int) ($need['quantity'] ?? 1),
                ]);
            }

            $supplier = Supplier::query()->firstOrCreate(
                ['school_id' => $schoolId, 'name' => trim($data['supplier_name'])],
                [
                    'contact' => $data['supplier_contact'] ?? null,
                    'commission_rate_bps' => (int) ($data['commission_rate_bps'] ?? 0),
                    'status' => 'active',
                ],
            );

            foreach ($data['packs'] as $pack) {
                $tier = KitPackTier::tryFrom($pack['tier']);
                if ($tier === null) {
                    throw new DomainException('Pack kit inconnu.');
                }
                $amount = (int) $pack['total_amount'];
                if ($amount < 1) {
                    throw new DomainException('Le montant du pack doit être un entier Ariary positif.');
                }
                KitPack::query()->create([
                    'school_id' => $schoolId,
                    'kit_definition_id' => $definition->id,
                    'supplier_id' => $supplier->id,
                    'tier' => $tier,
                    'total_amount' => $amount,
                ]);
            }

            Auditor::record('kit_definition.created', 'kit_definition', $definition->id);

            return $definition->load(['needs', 'packs.supplier', 'gradeLevel']);
        });
    }
}
