<?php

namespace App\Domain\Reliability\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrustEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'school_id',
        'event_type',
        'occurred_at',
        'source_type',
        'source_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public static function emit(
        string $subjectType,
        string $subjectId,
        string $eventType,
        ?string $schoolId = null,
        ?string $sourceType = null,
        ?string $sourceId = null,
        array $metadata = [],
    ): self {
        return self::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'school_id' => $schoolId,
            'event_type' => $eventType,
            'occurred_at' => now(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'metadata' => $metadata,
        ]);
    }
}
