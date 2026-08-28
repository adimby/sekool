<?php

namespace App\Domain\Collection\Models;

use App\Domain\Collection\Enums\RiskLevel;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskAssessment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'payer_account_id',
        'level',
        'outstanding_amount',
        'days_overdue',
        'on_time_ratio',
        'calculator_version',
        'computed_at',
        'manual_override_level',
        'override_reason',
        'override_until',
        'override_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'level' => RiskLevel::class,
            'outstanding_amount' => 'integer',
            'days_overdue' => 'integer',
            'on_time_ratio' => 'float',
            'computed_at' => 'datetime',
            'manual_override_level' => RiskLevel::class,
            'override_until' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function factors(): HasMany
    {
        return $this->hasMany(RiskFactor::class);
    }

    public function effectiveLevel(): RiskLevel
    {
        if ($this->manual_override_level instanceof RiskLevel
            && $this->override_until !== null
            && $this->override_until->isFuture()) {
            return $this->manual_override_level;
        }

        return $this->level;
    }
}
