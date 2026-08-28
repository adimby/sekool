<?php

namespace App\Domain\Certificate\Models;

use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateVerification extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'token_id',
        'verified_at',
        'ip_hash',
        'user_agent_hash',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(CertificateVerificationToken::class, 'token_id');
    }
}
