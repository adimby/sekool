<?php

namespace App\Domain\Academic\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'school_year_id',
        'grade_level_id',
        'name',
        'capacity',
        'series',
        'main_teacher_person_id',
        'delegate_person_id',
        'vice_delegate_person_id',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function mainTeacher(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'main_teacher_person_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'delegate_person_id');
    }

    public function viceDelegate(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'vice_delegate_person_id');
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(ClassroomTeacher::class);
    }

    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }

    public function councils(): HasMany
    {
        return $this->hasMany(ClassCouncil::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClassActivity::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
