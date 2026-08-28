<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\InstallmentStatus;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'invoice_id',
        'sequence',
        'due_on',
        'amount',
        'paid_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_on' => 'date',
            'amount' => 'integer',
            'paid_amount' => 'integer',
            'status' => InstallmentStatus::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function remainingAmount(): int
    {
        return $this->amount - $this->paid_amount;
    }

    public function applyPayment(int $ariary): void
    {
        $this->paid_amount += $ariary;
        $this->refreshDerivedStatus();
        $this->save();
    }

    public function refreshDerivedStatus(): void
    {
        if ($this->paid_amount >= $this->amount) {
            $this->status = InstallmentStatus::Paid;

            return;
        }

        if ($this->paid_amount > 0) {
            $this->status = InstallmentStatus::PartiallyPaid;

            return;
        }

        $this->status = $this->due_on->lt(now()->startOfDay())
            ? InstallmentStatus::Overdue
            : InstallmentStatus::Pending;
    }
}
