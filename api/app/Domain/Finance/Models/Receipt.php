<?php

namespace App\Domain\Finance\Models;

use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'payment_id',
        'number',
        'issued_at',
        'issued_by_person_id',
        'cancelled_by_receipt_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'issued_by_person_id');
    }
}
