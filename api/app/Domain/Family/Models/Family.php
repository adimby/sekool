<?php

namespace App\Domain\Family\Models;

use App\Domain\Identity\Models\Person;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    use HasUuids;

    protected $fillable = [
        'label',
        'primary_language',
        'created_by_person_id',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }
}
