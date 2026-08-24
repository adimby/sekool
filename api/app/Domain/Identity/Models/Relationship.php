<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relationship extends Model
{
    use HasUuids;

    protected $fillable = [
        'subject_person_id',
        'object_person_id',
        'type',
        'scopes',
        'status',
        'verification_method',
        'verified_by_person_id',
        'established_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => RelationshipType::class,
            'scopes' => 'array',
            'established_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'subject_person_id');
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'object_person_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
