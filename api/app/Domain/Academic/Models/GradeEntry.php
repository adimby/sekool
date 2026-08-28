<?php

namespace App\Domain\Academic\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeEntry extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'academic_term_id',
        'subject_id',
        'value',
        'max_value',
        'coefficient',
        'assessed_on',
        'recorded_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'max_value' => 'float',
            'coefficient' => 'float',
            'assessed_on' => 'date',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'recorded_by_person_id');
    }
}
