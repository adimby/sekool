<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\StudentAlertCategory;
use App\Domain\Academic\Enums\StudentAlertSeverity;
use App\Domain\Academic\Enums\StudentAlertStatus;
use App\Domain\Academic\Support\AlertCopy;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAlert extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'category',
        'severity',
        'reason_summary',
        'detected_at',
        'detector_version',
        'recommended_action',
        'status',
        'acknowledged_by_person_id',
        'acknowledged_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => StudentAlertCategory::class,
            'severity' => StudentAlertSeverity::class,
            'status' => StudentAlertStatus::class,
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'acknowledged_by_person_id');
    }

    public function signals(): HasMany
    {
        return $this->hasMany(StudentAlertSignal::class, 'alert_id');
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        $this->loadMissing('enrollment.person');

        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'student' => $this->enrollment?->person === null ? null : [
                'id' => $this->enrollment->person->id,
                'first_name' => $this->enrollment->person->first_name,
                'last_name' => $this->enrollment->person->last_name,
            ],
            'category' => $this->category->value,
            'severity' => $this->severity->value,
            'status' => $this->status->value,
            'reason_summary' => $this->reason_summary,
            'recommended_action' => $this->recommended_action,
            'detector_version' => $this->detector_version,
            'disclaimer' => AlertCopy::DISCLAIMER,
            'detected_at' => $this->detected_at?->toIso8601String(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
        ];
    }
}
