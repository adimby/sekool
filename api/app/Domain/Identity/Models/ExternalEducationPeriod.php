<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalEducationPeriod extends Model
{
    use HasVersion4Uuids;

    protected $fillable = [
        'person_id',
        'school_label',
        'starts_on',
        'ends_on',
        'declared_grade_level',
        'declared_by_person_id',
        'verification_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
