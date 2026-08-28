<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'fee_schedule_id',
        'code',
        'label',
        'amount',
        'due_on',
        'category',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'due_on' => 'date',
            'category' => FeeCategory::class,
            'is_recurring' => 'boolean',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(FeeSchedule::class, 'fee_schedule_id');
    }
}
