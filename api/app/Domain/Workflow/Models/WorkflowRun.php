<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRun extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'rule_id',
        'trigger_event_type',
        'trigger_event_id',
        'subject_type',
        'subject_id',
        'idempotency_key',
        'status',
        'started_at',
        'finished_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(WorkflowRule::class, 'rule_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class, 'run_id');
    }
}
