<?php

namespace App\Domain\Identity\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PersonLinkRequest extends Model
{
    use BelongsToTenant, HasUuids;

    protected $hidden = [
        'matched_person_id',
        'submitted_public_id_hash',
        'ip_hash',
    ];

    protected $fillable = [
        'school_id',
        'submitted_public_id_hash',
        'matched_person_id',
        'status',
        'requested_by_person_id',
        'ip_hash',
        'expires_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
