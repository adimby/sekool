<?php

namespace App\Domain\SchoolKit\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitPackItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'kit_pack_id',
        'need_id',
        'product_reference',
        'brand',
        'unit_amount',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'unit_amount' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(KitPack::class, 'kit_pack_id');
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(KitNeed::class, 'need_id');
    }
}
