<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\School;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeLevel extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'name',
        'stage',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'stage' => GradeStage::class,
            'sequence' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }
}
