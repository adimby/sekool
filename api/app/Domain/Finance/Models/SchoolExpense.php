<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\ExpenseCategory;
use App\Domain\Finance\Enums\ExpenseKind;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolExpense extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'school_year_id',
        'kind',
        'label',
        'category',
        'amount',
        'spent_on',
        'vendor',
        'notes',
        'recorded_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ExpenseKind::class,
            'category' => ExpenseCategory::class,
            'amount' => 'integer',
            'spent_on' => 'date',
        ];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'recorded_by_person_id');
    }
}
