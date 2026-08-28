<?php

namespace App\Domain\Communication\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'template_key',
        'subject_person_id',
        'recipient_person_id',
        'channel',
        'payload',
        'queued_at',
        'sent_at',
        'priority',
        'workflow_run_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MessageDelivery::class);
    }
}
