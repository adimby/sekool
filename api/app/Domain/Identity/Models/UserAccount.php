<?php

namespace App\Domain\Identity\Models;

use Database\Factories\UserAccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class UserAccount extends Authenticatable
{
    /** @use HasFactory<UserAccountFactory> */
    use HasApiTokens, HasFactory, HasUuids;

    protected static function newFactory(): UserAccountFactory
    {
        return UserAccountFactory::new();
    }

    protected $fillable = [
        'person_id',
        'email',
        'phone_e164',
        'password',
        'totp_enabled_at',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'totp_secret_encrypted',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'totp_enabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'must_change_password' => 'boolean',
            'failed_attempts' => 'integer',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
