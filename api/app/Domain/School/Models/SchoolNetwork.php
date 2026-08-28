<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolNetwork extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
    ];

    public function schools(): HasMany
    {
        return $this->hasMany(School::class, 'network_id');
    }
}
