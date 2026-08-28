<?php

namespace App\Domain\SchoolKit\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\SchoolKit\Enums\KitPackTier;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitPack extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'kit_definition_id',
        'supplier_id',
        'tier',
        'total_amount',
        'available_from',
        'available_until',
    ];

    protected function casts(): array
    {
        return [
            'tier' => KitPackTier::class,
            'total_amount' => 'integer',
            'available_from' => 'date',
            'available_until' => 'date',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(KitDefinition::class, 'kit_definition_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitPackItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(KitOrder::class);
    }
}
