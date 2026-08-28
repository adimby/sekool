<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\PaymentMethod;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'payer_account_id',
        'amount',
        'method',
        'received_on',
        'reference',
        'recorded_by_person_id',
        'idempotency_key',
        'reversed_by_payment_id',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'received_on' => 'date',
        ];
    }

    public function payerAccount(): BelongsTo
    {
        return $this->belongsTo(PayerAccount::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'recorded_by_person_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
