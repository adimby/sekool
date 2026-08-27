<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\ClassCouncilStatus;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCouncil extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'academic_term_id',
        'held_on',
        'title',
        'minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'status' => ClassCouncilStatus::class,
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }
}
