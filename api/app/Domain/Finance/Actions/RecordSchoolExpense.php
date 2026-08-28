<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\ExpenseCategory;
use App\Domain\Finance\Enums\ExpenseKind;
use App\Domain\Finance\Models\SchoolExpense;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\SchoolYear;

final class RecordSchoolExpense
{
    /**
     * @param  array{
     *     school_year_id: string,
     *     kind: string,
     *     label: string,
     *     category?: string,
     *     amount: int,
     *     spent_on: string,
     *     vendor?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function execute(string $schoolId, string $actorPersonId, array $data): SchoolExpense
    {
        $year = SchoolYear::query()->find($data['school_year_id']);
        if ($year === null || (string) $year->school_id !== $schoolId) {
            throw new DomainException('Année scolaire introuvable.', 404);
        }

        $kind = ExpenseKind::tryFrom($data['kind']);
        if ($kind === null) {
            throw new DomainException('Précisez s’il s’agit d’un achat ou d’une dépense.');
        }

        $category = ExpenseCategory::tryFrom((string) ($data['category'] ?? ExpenseCategory::Other->value))
            ?? ExpenseCategory::Other;

        $amount = (int) $data['amount'];
        if ($amount <= 0) {
            throw new DomainException('Le montant doit être un entier Ariary strictement positif.');
        }

        $expense = SchoolExpense::query()->create([
            'school_id' => $schoolId,
            'school_year_id' => $year->id,
            'kind' => $kind,
            'label' => trim($data['label']),
            'category' => $category,
            'amount' => $amount,
            'spent_on' => $data['spent_on'],
            'vendor' => isset($data['vendor']) && trim((string) $data['vendor']) !== '' ? trim((string) $data['vendor']) : null,
            'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            'recorded_by_person_id' => $actorPersonId,
        ]);

        Auditor::record('school_expense.recorded', 'school_expense', $expense->id, null, [
            'kind' => $kind->value,
            'amount' => $amount,
        ]);

        return $expense;
    }
}
