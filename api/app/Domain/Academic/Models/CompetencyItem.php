<?php

namespace App\Domain\Academic\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetencyItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'domain_id',
        'label',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(CompetencyDomain::class, 'domain_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(CompetencyAssessment::class, 'competency_item_id');
    }
}
