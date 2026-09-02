<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\SchoolEventAudience;
use App\Domain\Academic\Enums\SchoolEventType;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolEvent extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'type',
        'title',
        'body',
        'starts_on',
        'ends_on',
        'audience',
        'grade_level_id',
        'classroom_id',
        'location',
        'created_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => SchoolEventType::class,
            'audience' => SchoolEventAudience::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }
}
