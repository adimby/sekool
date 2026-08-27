<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\ClassActivityType;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassActivity extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'type',
        'title',
        'held_on',
        'location',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClassActivityType::class,
            'held_on' => 'date',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
