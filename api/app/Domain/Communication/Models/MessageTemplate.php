<?php

namespace App\Domain\Communication\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'key',
        'channel',
        'locale',
        'subject',
        'body',
        'version',
    ];
}
