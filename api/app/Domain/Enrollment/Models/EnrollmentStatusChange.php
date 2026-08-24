<?php

namespace App\Domain\Enrollment\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentStatusChange extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'from_status',
        'to_status',
        'reason',
        'occurred_at',
        'actor_person_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
