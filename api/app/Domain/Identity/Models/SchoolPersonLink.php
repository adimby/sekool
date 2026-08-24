<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolPersonLink extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'person_id',
        'kind',
        'source',
        'grants_contact_access',
        'established_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SchoolPersonLinkKind::class,
            'source' => SchoolPersonLinkSource::class,
            'grants_contact_access' => 'boolean',
            'established_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
