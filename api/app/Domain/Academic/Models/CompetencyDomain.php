<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\Platform\Tenancy\HasReadyTable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetencyDomain extends Model
{
    use BelongsToTenant, HasReadyTable, HasUuids;

    protected $fillable = [
        'school_id',
        'stage',
        'code',
        'label',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'stage' => GradeStage::class,
            'sequence' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompetencyItem::class, 'domain_id')->orderBy('sequence');
    }
}
