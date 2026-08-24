<?php

namespace App\Domain\Consent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConsentEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'consent_id',
        'event',
        'occurred_at',
        'actor_person_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
