<?php

namespace App\Domain\Consent\Models;

use App\Domain\Consent\Enums\ConsentScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consent extends Model
{
    use HasUuids;

    protected $fillable = [
        'subject_person_id',
        'granted_by_person_id',
        'grantee_school_id',
        'scope',
        'purpose',
        'granted_at',
        'expires_at',
        'revoked_at',
        'source',
        'terms_version',
    ];

    protected function casts(): array
    {
        return [
            'scope' => ConsentScope::class,
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(ConsentEvent::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
