<?php

namespace App\Domain\Enrollment\Models;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'school_year_id',
        'classroom_id',
        'person_id',
        'student_number',
        'status',
        'enrolled_on',
        'ended_on',
        'exit_reason',
        'source_type',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'enrolled_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(EnrollmentStatusChange::class);
    }

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Active;
    }
}
