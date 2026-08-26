<?php

namespace App\Domain\Finance\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'invoice_id',
        'fee_item_id',
        'label',
        'amount',
        'discount_amount',
        'discount_reason',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'discount_amount' => 'integer',
            'sequence' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    public function netAmount(): int
    {
        return $this->amount - $this->discount_amount;
    }
}
