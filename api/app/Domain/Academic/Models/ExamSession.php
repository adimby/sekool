<?php

namespace App\Domain\Academic\Models;

use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\Platform\Tenancy\HasReadyTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSession extends Model
{
    use BelongsToTenant, HasReadyTable, HasUuids;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'title',
        'subject',
        'held_on',
        'starts_at',
        'ends_at',
        'room',
        'body',
        'created_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }
}
