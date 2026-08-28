<?php

namespace App\Domain\Finance\Models;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Finance\Enums\FeeAdjustmentType;
use App\Domain\Finance\Enums\FeeScheduleStatus;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeSchedule extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'school_year_id',
        'grade_level_id',
        'name',
        'status',
        'copied_from_schedule_id',
        'adjustment_type',
        'adjustment_amount',
        'adjustment_percent_bps',
        'submitted_at',
        'submitted_by_person_id',
        'locked_at',
        'locked_by_person_id',
        'unlock_requested_at',
        'unlock_requested_by_person_id',
        'unlock_request_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => FeeScheduleStatus::class,
            'adjustment_type' => FeeAdjustmentType::class,
            'adjustment_amount' => 'integer',
            'adjustment_percent_bps' => 'integer',
            'submitted_at' => 'datetime',
            'locked_at' => 'datetime',
            'unlock_requested_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null
            || $this->status === FeeScheduleStatus::Active;
    }

    public function isEditable(): bool
    {
        return ! $this->isLocked();
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_schedule_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeItem::class);
    }
}
