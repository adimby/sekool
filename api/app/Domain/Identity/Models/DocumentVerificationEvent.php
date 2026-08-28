<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentVerificationEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'document_id',
        'school_id',
        'from_status',
        'to_status',
        'actor_person_id',
        'actor_school_id',
        'method',
        'evidence',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
