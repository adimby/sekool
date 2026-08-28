<?php

namespace App\Domain\SchoolKit\Actions;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\SchoolYear;
use App\Domain\SchoolKit\Enums\KitPackTier;
use App\Domain\SchoolKit\Enums\KitPriceSource;
use App\Domain\SchoolKit\Models\KitDefinition;
use App\Domain\SchoolKit\Models\KitNeed;
use App\Domain\SchoolKit\Models\KitPack;
use App\Domain\SchoolKit\Models\KitPackItem;
use App\Domain\SchoolKit\Models\Supplier;
use Illuminate\Support\Facades\DB;

final class SaveKitCatalog
{
    /**
     * @param  array{
     *     school_year_id: string,
     *     grade_level_id?: string|null,
     *     name?: string|null,
     *     price_source?: string,
     *     copied_from_id?: string|null,
     *     needs?: list<array{
     *         label: string,
     *         quantity?: int,
     *         notes?: string|null,
     *         offers?: list<array{tier: string, brand?: string|null, unit_amount?: int, quantity?: int}>
     *     }>,
     *     supplier_name?: string|null,
     *     supplier_contact?: string|null,
     *     commission_rate_bps?: int,
     *     packs?: list<array{tier: string, total_amount?: int}>
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
            $grade = null;
            if (is_string($gradeId) && $gradeId !== '') {
                $grade = GradeLevel::query()->find($gradeId);
                if ($grade === null || (string) $grade->school_id !== $schoolId) {
                    throw new DomainException('Niveau introuvable.', 404);
                }
            } else {
                $gradeId = null;
            }

            $source = KitPriceSource::tryFrom((string) ($data['price_source'] ?? KitPriceSource::Supplier->value))
                ?? KitPriceSource::Supplier;

            $supplierName = trim((string) ($data['supplier_name'] ?? ''));
            if ($supplierName === '') {
                $supplierName = $source === KitPriceSource::Purchasing ? 'Service achat' : 'Fournisseur';
            }

            $supplier = Supplier::query()->firstOrCreate(
                ['school_id' => $schoolId, 'name' => $supplierName],
                [
                    'contact' => $data['supplier_contact'] ?? null,
                    'commission_rate_bps' => (int) ($data['commission_rate_bps'] ?? 0),
                    'status' => 'active',
                ],
            );

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $name = 'Fournitures'.($grade !== null ? ' '.$grade->name : '');
            }

            $query = KitDefinition::query()
                ->where('school_year_id', $year->id);
            if ($gradeId !== null) {
                $query->where('grade_level_id', $gradeId);
            } else {
                $query->whereNull('grade_level_id')->where('name', $name);
            }

            $definition = $query->first();
            $created = $definition === null;
            if ($definition === null) {
                $definition = KitDefinition::query()->create([
                    'school_id' => $schoolId,
                    'school_year_id' => $year->id,
                    'grade_level_id' => $gradeId,
                    'name' => $name,
                    'status' => 'active',
                    'price_source' => $source,
                    'copied_from_id' => $data['copied_from_id'] ?? null,
                ]);
            } else {
                $definition->forceFill([
                    'name' => $name,
                    'price_source' => $source,
                    'status' => 'active',
                ])->save();
            }

            $this->replaceNeedsAndPacks($schoolId, $definition, $supplier, $data);

            Auditor::record(
                $created ? 'kit_definition.created' : 'kit_definition.updated',
                'kit_definition',
                $definition->id,
            );

            return $definition->load(['needs', 'packs.supplier', 'packs.items', 'gradeLevel']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function replaceNeedsAndPacks(string $schoolId, KitDefinition $definition, Supplier $supplier, array $data): void
    {
        $packIds = $definition->packs()->pluck('id');
        KitPackItem::query()->whereIn('kit_pack_id', $packIds)->delete();
        KitNeed::query()->where('kit_definition_id', $definition->id)->delete();

        $needsInput = $data['needs'] ?? [];
        $needs = [];
        foreach ($needsInput as $need) {
            $label = trim((string) $need['label']);
            if ($label === '') {
                continue;
            }
            $needs[] = [
                'model' => KitNeed::query()->create([
                    'school_id' => $schoolId,
                    'kit_definition_id' => $definition->id,
                    'label' => $label,
                    'quantity' => max(1, (int) ($need['quantity'] ?? 1)),
                    'notes' => isset($need['notes']) && is_string($need['notes']) ? $need['notes'] : null,
                ]),
                'offers' => $need['offers'] ?? [],
            ];
        }

        $legacyPacks = [];
        foreach ($data['packs'] ?? [] as $pack) {
            $tier = KitPackTier::parse((string) ($pack['tier'] ?? ''));
            if ($tier !== null) {
                $legacyPacks[$tier->value] = (int) ($pack['total_amount'] ?? 0);
            }
        }

        $totals = [];
        $hasOffers = false;
        foreach ($needs as $row) {
            foreach ($row['offers'] as $offer) {
                $tier = KitPackTier::parse((string) ($offer['tier'] ?? ''));
                if ($tier === null) {
                    continue;
                }
                $hasOffers = true;
                $qty = max(1, (int) ($offer['quantity'] ?? $row['model']->quantity));
                $unit = (int) ($offer['unit_amount'] ?? 0);
                $totals[$tier->value] = ($totals[$tier->value] ?? 0) + ($unit * $qty);
            }
        }

        $tierValues = $hasOffers
            ? array_keys($totals)
            : array_keys($legacyPacks);

        if ($tierValues === []) {
            throw new DomainException('Indiquez au moins une gamme (éco, standard ou luxe).');
        }

        $keptTiers = [];
        foreach ($tierValues as $tierValue) {
            $tier = KitPackTier::parse($tierValue);
            if ($tier === null) {
                throw new DomainException('Pack kit inconnu.');
            }
            $amount = $hasOffers ? (int) ($totals[$tier->value] ?? 0) : (int) ($legacyPacks[$tier->value] ?? 0);
            if ($amount < 1) {
                throw new DomainException('Chaque gamme doit avoir un montant Ariary positif (marque × quantité).');
            }

            $pack = KitPack::query()->updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'kit_definition_id' => $definition->id,
                    'tier' => $tier->value,
                ],
                [
                    'supplier_id' => $supplier->id,
                    'total_amount' => $amount,
                ],
            );
            $keptTiers[] = $tier->value;

            if ($hasOffers) {
                foreach ($needs as $row) {
                    foreach ($row['offers'] as $offer) {
                        $offerTier = KitPackTier::parse((string) ($offer['tier'] ?? ''));
                        if ($offerTier !== $tier) {
                            continue;
                        }
                        $qty = max(1, (int) ($offer['quantity'] ?? $row['model']->quantity));
                        KitPackItem::query()->create([
                            'school_id' => $schoolId,
                            'kit_pack_id' => $pack->id,
                            'need_id' => $row['model']->id,
                            'brand' => isset($offer['brand']) ? trim((string) $offer['brand']) : null,
                            'product_reference' => isset($offer['product_reference']) ? trim((string) $offer['product_reference']) : null,
                            'unit_amount' => (int) ($offer['unit_amount'] ?? 0),
                            'quantity' => $qty,
                        ]);
                    }
                }
            }
        }

        KitPack::query()
            ->where('kit_definition_id', $definition->id)
            ->whereNotIn('tier', $keptTiers)
            ->whereDoesntHave('orders')
            ->delete();
    }
}
