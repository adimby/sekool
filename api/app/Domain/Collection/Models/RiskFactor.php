<?php

namespace App\Domain\Collection\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskFactor extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'risk_assessment_id',
        'factor_key',
        'human_label',
        'contribution',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'contribution' => 'integer',
            'evidence' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(RiskAssessment::class, 'risk_assessment_id');
    }
}
