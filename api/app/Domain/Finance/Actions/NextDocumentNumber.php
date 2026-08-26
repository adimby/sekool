<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\DocumentType;
use App\Domain\Finance\Models\NumberingSequence;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class NextDocumentNumber
{
    public function allocate(string $schoolId, string $schoolYearId, DocumentType $type): string
    {
        $seq = NumberingSequence::query()
            ->where('school_year_id', $schoolYearId)
            ->where('document_type', $type)
            ->lockForUpdate()
            ->first();

        if ($seq === null) {
            try {
                $seq = DB::transaction(fn () => NumberingSequence::query()->create([
                    'school_id' => $schoolId,
                    'school_year_id' => $schoolYearId,
                    'document_type' => $type,
                    'next_number' => 1,
                ]));
            } catch (UniqueConstraintViolationException) {
                $seq = NumberingSequence::query()
                    ->where('school_year_id', $schoolYearId)
                    ->where('document_type', $type)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $n = $seq->next_number;
        $seq->next_number = $n + 1;
        $seq->save();

        $year = SchoolYear::query()->find($schoolYearId);
        if ($year === null) {
            throw new DomainException('Année scolaire introuvable.', 404);
        }

        $prefix = $type === DocumentType::Receipt ? 'REC' : 'FAC';
        $yearCode = explode('-', $year->label)[0];

        return sprintf('%s-%s-%06d', $prefix, $yearCode, $n);
    }
}
