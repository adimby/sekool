<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'date',
        'session',
        'status',
        'minutes_late',
        'reason',
        'recorded_by_person_id',
        'recorded_via',
        'client_reference',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'session' => AttendanceSession::class,
            'status' => AttendanceStatus::class,
            'minutes_late' => 'integer',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'recorded_by_person_id');
    }
}
