<?php

namespace App\Domain\Academic\Models;

use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSubstitution extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'timetable_slot_id',
        'classroom_id',
        'on_date',
        'substitute_person_id',
        'reason',
        'created_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'on_date' => 'date',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'substitute_person_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }
}
