<?php

namespace App\Domain\Academic\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAlertSignal extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'alert_id',
        'signal_type',
        'observed_value',
        'baseline_value',
        'window_start',
        'window_end',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'observed_value' => 'float',
            'baseline_value' => 'float',
            'window_start' => 'date',
            'window_end' => 'date',
            'evidence' => 'array',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(StudentAlert::class, 'alert_id');
    }
}
