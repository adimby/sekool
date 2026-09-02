<?php

namespace App\Domain\Identity\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityMerge extends Model
{
    use BelongsToTenant, HasUuids;

    public const REQUESTED = 'requested';

    public const MERGED = 'merged';

    public const REFUSED = 'refused';

    public const UNDONE = 'undone';

    protected $fillable = [
        'school_id',
        'surviving_person_id',
        'duplicate_person_id',
        'reason',
        'requested_by_person_id',
        'status',
        'decided_by_person_id',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function surviving(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'surviving_person_id');
    }

    public function duplicate(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'duplicate_person_id');
    }
}
