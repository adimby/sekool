<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\CompetencyLevel;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetencyAssessment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'classroom_id',
        'competency_item_id',
        'academic_term_id',
        'level',
        'comment',
        'assessed_on',
        'recorded_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'level' => CompetencyLevel::class,
            'assessed_on' => 'date',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CompetencyItem::class, 'competency_item_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'recorded_by_person_id');
    }
}
