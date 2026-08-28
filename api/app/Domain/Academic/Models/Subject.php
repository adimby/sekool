<?php

namespace App\Domain\Academic\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'name',
    ];

    public function gradeEntries(): HasMany
    {
        return $this->hasMany(GradeEntry::class);
    }
}
