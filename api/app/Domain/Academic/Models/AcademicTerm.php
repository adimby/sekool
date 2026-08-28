<?php

namespace App\Domain\Academic\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicTerm extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'school_year_id',
        'label',
        'sequence',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
