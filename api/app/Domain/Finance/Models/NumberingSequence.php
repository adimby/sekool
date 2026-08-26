<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\DocumentType;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberingSequence extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'school_year_id',
        'document_type',
        'next_number',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'next_number' => 'integer',
        ];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
