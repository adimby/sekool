<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRule extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'template_key',
        'enabled',
        'params',
        'version',
        'dry_run',
        'daily_action_cap',
        'quiet_hours',
        'updated_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'params' => 'array',
            'version' => 'integer',
            'dry_run' => 'boolean',
            'daily_action_cap' => 'integer',
            'quiet_hours' => 'array',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class, 'rule_id');
    }
}
