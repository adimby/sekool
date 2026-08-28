<?php

namespace App\Domain\SchoolKit\Actions;

use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\SchoolYear;
use App\Domain\SchoolKit\Models\KitDefinition;
use App\Domain\SchoolKit\Models\KitPackItem;
use App\Domain\SchoolKit\Models\Supplier;

final class CopyKitListsFromYear
{
    public function __construct(private readonly SaveKitCatalog $save) {}

    /**
     * @param  list<string>|null  $gradeIds  null = every grade
     * @return list<KitDefinition>
     */
    public function execute(string $schoolId, string $sourceYearId, string $targetYearId, ?array $gradeIds = null): array
    {
        if ($sourceYearId === $targetYearId) {
            throw new DomainException('Choisissez l’année précédente, pas la même année.');
        }

        $sourceYear = SchoolYear::query()->find($sourceYearId);
        $targetYear = SchoolYear::query()->find($targetYearId);
        if ($sourceYear === null || (string) $sourceYear->school_id !== $schoolId) {
            throw new DomainException('Année source introuvable.', 404);
        }
        if ($targetYear === null || (string) $targetYear->school_id !== $schoolId) {
            throw new DomainException('Année cible introuvable.', 404);
        }

        $sources = KitDefinition::query()
            ->with(['needs', 'packs.supplier', 'packs.items', 'gradeLevel'])
            ->where('school_year_id', $sourceYearId)
            ->when($gradeIds !== null, fn ($query) => $query->whereIn('grade_level_id', $gradeIds))
            ->orderBy('name')
            ->get();

        if ($sources->isEmpty()) {
            throw new DomainException('Aucune liste de fournitures à reprendre pour l’année source.');
        }

        $created = [];
        foreach ($sources as $source) {
            if ($source->grade_level_id !== null) {
                $exists = KitDefinition::query()
                    ->where('school_year_id', $targetYearId)
                    ->where('grade_level_id', $source->grade_level_id)
                    ->exists();
                if ($exists) {
                    continue;
                }
            }

            $supplier = $source->packs->first()?->supplier
                ?? Supplier::query()->where('school_id', $schoolId)->orderBy('name')->first();

            $needs = $source->needs->map(function ($need) use ($source): array {
                $offers = [];
                foreach ($source->packs as $pack) {
                    $item = $pack->items->firstWhere('need_id', $need->id);
                    if (! $item instanceof KitPackItem) {
                        continue;
                    }
                    $tier = $pack->tier;
                    $offers[] = [
                        'tier' => is_object($tier) ? $tier->value : (string) $tier,
                        'brand' => $item->brand,
                        'unit_amount' => (int) $item->unit_amount,
                        'quantity' => (int) $item->quantity,
                    ];
                }

                return [
                    'label' => $need->label,
                    'quantity' => (int) $need->quantity,
                    'notes' => $need->notes,
                    'offers' => $offers,
                ];
            })->all();

            $packs = $source->packs->map(fn ($pack): array => [
                'tier' => is_object($pack->tier) ? $pack->tier->value : (string) $pack->tier,
                'total_amount' => (int) $pack->total_amount,
            ])->all();

            $created[] = $this->save->execute($schoolId, [
                'school_year_id' => $targetYearId,
                'grade_level_id' => $source->grade_level_id,
                'name' => $source->name,
                'price_source' => is_object($source->price_source) ? $source->price_source->value : (string) ($source->price_source ?? 'supplier'),
                'copied_from_id' => $source->id,
                'supplier_name' => $supplier?->name,
                'supplier_contact' => $supplier?->contact,
                'commission_rate_bps' => (int) ($supplier?->commission_rate_bps ?? 0),
                'needs' => $needs,
                'packs' => $packs,
            ]);
        }

        if ($created === []) {
            throw new DomainException('Les listes de cette année existent déjà.');
        }

        Auditor::record('kit_definition.copied_year', 'school_year', $targetYear->id, null, [
            'source_year_id' => $sourceYearId,
            'count' => count($created),
        ]);

        return $created;
    }
}
