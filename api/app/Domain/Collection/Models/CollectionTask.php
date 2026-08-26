<?php

namespace App\Domain\Collection\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionTask extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'template_key',
        'title',
        'reason_summary',
        'priority',
        'status',
        'workflow_run_id',
        'claimed_by_person_id',
        'claimed_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
