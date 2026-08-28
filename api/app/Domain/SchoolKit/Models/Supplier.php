<?php

namespace App\Domain\SchoolKit\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'name',
        'contact',
        'commission_rate_bps',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate_bps' => 'integer',
        ];
    }

    public function packs(): HasMany
    {
        return $this->hasMany(KitPack::class);
    }
}
