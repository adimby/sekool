<?php

namespace App\Domain\School\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): SchoolFactory
    {
        return SchoolFactory::new();
    }

    protected $fillable = [
        'network_id',
        'name',
        'short_name',
        'code',
        'city',
        'region',
        'phone_e164',
        'email',
        'timezone',
        'currency',
        'locale',
        'status',
        'plan',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(SchoolNetwork::class, 'network_id');
    }

    public function years(): HasMany
    {
        return $this->hasMany(SchoolYear::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(SchoolRoleAssignment::class);
    }
}
