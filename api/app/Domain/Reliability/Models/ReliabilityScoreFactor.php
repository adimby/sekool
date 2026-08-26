<?php

namespace App\Domain\Reliability\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReliabilityScoreFactor extends Model
{
    use HasUuids;

    protected $fillable = [
        'score_id',
        'event_type',
        'human_label',
        'contribution',
        'event_count',
        'sample_event_ids',
    ];

    protected function casts(): array
    {
        return [
            'contribution' => 'integer',
            'event_count' => 'integer',
            'sample_event_ids' => 'array',
        ];
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(ReliabilityScore::class, 'score_id');
    }
}
