<?php

namespace App\Domain\Finance\Support;

use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Finance\Models\FeeItem;
use App\Domain\Finance\Models\FeeSchedule;
use App\Domain\Finance\Models\InvoiceLine;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Str;

final class SyncFeeItems
{
    /**
     * @param  list<array{
     *     id?: string,
     *     code?: string,
     *     label: string,
     *     amount: int,
     *     due_on: string,
     *     category?: string,
     *     is_recurring?: bool
     * }>  $items
     */
    public function execute(FeeSchedule $schedule, array $items): void
    {
        if ($items === []) {
            throw new DomainException('Un barème doit contenir au moins une ligne de frais.');
        }

        $keptCodes = [];

        foreach (array_values($items) as $index => $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                throw new DomainException('Chaque ligne de frais doit avoir un libellé.');
            }

            $amount = (int) ($item['amount'] ?? 0);
            if ($amount <= 0) {
                throw new DomainException('Chaque montant doit être un entier Ariary strictement positif.');
            }

            $dueOn = $item['due_on'] ?? null;
            if (! is_string($dueOn) || $dueOn === '') {
                throw new DomainException('Chaque ligne de frais doit avoir une échéance.');
            }

            $category = FeeCategory::tryFrom((string) ($item['category'] ?? FeeCategory::Other->value));
            if ($category === null) {
                throw new DomainException('Type de frais inconnu.');
            }

            $code = strtoupper(trim((string) ($item['code'] ?? '')));
            if ($code === '') {
                $code = $this->codeFromLabel($label, $index);
            }

            if (in_array($code, $keptCodes, true)) {
                $code = $code.'_'.($index + 1);
            }

            $attrs = [
                'label' => $label,
                'amount' => $amount,
                'due_on' => $dueOn,
                'category' => $category,
                'is_recurring' => (bool) ($item['is_recurring'] ?? false),
            ];

            $existing = FeeItem::query()
                ->where('fee_schedule_id', $schedule->id)
                ->where('code', $code)
                ->first();

            if ($existing !== null) {
                $existing->fill($attrs)->save();
            } else {
                FeeItem::query()->create([
                    'school_id' => $schedule->school_id,
                    'fee_schedule_id' => $schedule->id,
                    'code' => $code,
                    ...$attrs,
                ]);
            }

            $keptCodes[] = $code;
        }

        $obsolete = FeeItem::query()
            ->where('fee_schedule_id', $schedule->id)
            ->whereNotIn('code', $keptCodes)
            ->get();

        foreach ($obsolete as $row) {
            if (InvoiceLine::query()->where('fee_item_id', $row->id)->exists()) {
                throw new DomainException('Impossible de retirer une ligne déjà facturée.');
            }

            $row->delete();
        }
    }

    private function codeFromLabel(string $label, int $index): string
    {
        $slug = strtoupper((string) Str::of($label)->ascii()->slug('_'));
        $slug = substr($slug, 0, 24);

        if ($slug === '') {
            $slug = 'FRAIS';
        }

        return $slug.'_'.($index + 1);
    }
}
