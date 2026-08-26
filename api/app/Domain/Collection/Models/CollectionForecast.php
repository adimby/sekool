<?php

namespace App\Domain\Collection\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CollectionForecast extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'week_starting_on',
        'expected_amount',
        'confidence_low_amount',
        'confidence_high_amount',
        'method_version',
        'computed_at',
        'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'week_starting_on' => 'date',
            'expected_amount' => 'integer',
            'confidence_low_amount' => 'integer',
            'confidence_high_amount' => 'integer',
            'computed_at' => 'datetime',
            'breakdown' => 'array',
        ];
    }
}
