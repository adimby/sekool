<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\ClassPostKind;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassPost extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'kind',
        'title',
        'body',
        'due_on',
        'held_on',
        'attachment_name',
        'attachment_body',
        'created_by_person_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ClassPostKind::class,
            'due_on' => 'date',
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
