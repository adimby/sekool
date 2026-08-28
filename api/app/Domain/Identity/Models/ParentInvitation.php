<?php

namespace App\Domain\Identity\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentInvitation extends Model
{
    use BelongsToTenant, HasUuids;

    protected $hidden = [
        'code_hash',
    ];

    protected $fillable = [
        'school_id',
        'person_id',
        'code_hash',
        'created_by_person_id',
        'expires_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
