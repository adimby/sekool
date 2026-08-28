<?php

namespace App\Domain\SchoolKit\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\SchoolKit\Enums\KitOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitOrder extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'payer_account_id',
        'enrollment_id',
        'kit_pack_id',
        'supplier_id',
        'status',
        'total_amount',
        'commission_amount',
        'placed_at',
        'placed_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => KitOrderStatus::class,
            'total_amount' => 'integer',
            'commission_amount' => 'integer',
            'placed_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(KitPack::class, 'kit_pack_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
