<?php

namespace App\Domain\SchoolKit\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitNeed extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'kit_definition_id',
        'label',
        'quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(KitDefinition::class, 'kit_definition_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitPackItem::class, 'need_id');
    }
}
