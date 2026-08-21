<?php

namespace App\Domain\School\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Database\Factories\SchoolYearFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolYear extends Model
{
    /** @use HasFactory<SchoolYearFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected static function newFactory(): SchoolYearFactory
    {
        return SchoolYearFactory::new();
    }

    protected $fillable = [
        'school_id',
        'label',
        'starts_on',
        'ends_on',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
