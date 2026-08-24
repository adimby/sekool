<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\PersonRoleType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonRole extends Model
{
    use HasUuids;

    protected $fillable = [
        'person_id',
        'role',
        'acquired_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => PersonRoleType::class,
            'acquired_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
