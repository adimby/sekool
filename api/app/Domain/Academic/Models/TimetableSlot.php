<?php

namespace App\Domain\Academic\Models;

use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSlot extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'weekday',
        'starts_at',
        'ends_at',
        'subject',
        'teacher_person_id',
        'room',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'teacher_person_id');
    }
}
