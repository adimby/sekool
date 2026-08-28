<?php

namespace App\Domain\Finance\Models;

use App\Domain\Family\Models\Family;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayerAccount extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'family_id',
        'responsible_person_id',
        'credit_balance_ariary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'credit_balance_ariary' => 'integer',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'responsible_person_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
