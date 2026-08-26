<?php

namespace App\Domain\Reliability\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReliabilityScore extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'subject_type',
        'subject_id',
        'index_type',
        'value',
        'band',
        'calculator_version',
        'computed_at',
        'inputs_digest',
        'event_count',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'event_count' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    public function factors(): HasMany
    {
        return $this->hasMany(ReliabilityScoreFactor::class, 'score_id');
    }
}
