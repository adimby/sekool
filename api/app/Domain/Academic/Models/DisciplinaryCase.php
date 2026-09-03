<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\DisciplinaryCaseStatus;
use App\Domain\Academic\Enums\DisciplinaryMeasureType;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\Platform\Tenancy\HasReadyTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryCase extends Model
{
    use BelongsToTenant, HasReadyTable, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'classroom_id',
        'occurred_on',
        'fact',
        'measure_type',
        'measure_label',
        'measure_on',
        'status',
        'follow_up',
        'created_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'measure_on' => 'date',
            'measure_type' => DisciplinaryMeasureType::class,
            'status' => DisciplinaryCaseStatus::class,
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }
}
