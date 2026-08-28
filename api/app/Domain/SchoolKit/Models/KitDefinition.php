<?php

namespace App\Domain\SchoolKit\Models;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitDefinition extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'school_year_id',
        'grade_level_id',
        'name',
        'status',
    ];

    public function year(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function needs(): HasMany
    {
        return $this->hasMany(KitNeed::class);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(KitPack::class);
    }
}
