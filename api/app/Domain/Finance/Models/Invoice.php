<?php

namespace App\Domain\Finance\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Enums\InvoiceStatus;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'payer_account_id',
        'school_year_id',
        'number',
        'issued_on',
        'total_amount',
        'discount_amount',
        'discount_reason',
        'net_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'total_amount' => 'integer',
            'discount_amount' => 'integer',
            'net_amount' => 'integer',
            'status' => InvoiceStatus::class,
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function payerAccount(): BelongsTo
    {
        return $this->belongsTo(PayerAccount::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('sequence');
    }

    public function paidAmount(): int
    {
        return (int) $this->installments()->sum('paid_amount');
    }

    public function remainingAmount(): int
    {
        return $this->net_amount - $this->paidAmount();
    }

    public function refreshPaymentStatus(): void
    {
        $paid = $this->paidAmount();

        $this->status = match (true) {
            $paid <= 0 => InvoiceStatus::Issued,
            $paid >= $this->net_amount => InvoiceStatus::Paid,
            default => InvoiceStatus::PartiallyPaid,
        };
        $this->save();
    }
}
