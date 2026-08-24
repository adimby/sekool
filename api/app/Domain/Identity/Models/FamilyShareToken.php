<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyShareToken extends Model
{
    use HasUuids;

    protected $hidden = [
        'token_hash',
    ];

    protected $fillable = [
        'created_by_person_id',
        'token_hash',
        'child_person_ids',
        'scopes',
        'target_school_id',
        'expires_at',
        'redeemed_at',
        'redeemed_by_school_id',
        'redeemed_by_person_id',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'child_person_ids' => 'array',
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    public function isRedeemable(): bool
    {
        return $this->redeemed_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}
