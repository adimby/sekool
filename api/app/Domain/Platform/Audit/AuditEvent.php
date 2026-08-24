<?php

namespace App\Domain\Platform\Audit;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'occurred_at',
        'actor_person_id',
        'actor_school_id',
        'actor_role',
        'action',
        'resource_type',
        'resource_id',
        'subject_person_id',
        'context',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public static function record(array $attributes): self
    {
        return self::query()->create(array_merge([
            'occurred_at' => now(),
            'outcome' => 'allowed',
        ], $attributes));
    }
}
