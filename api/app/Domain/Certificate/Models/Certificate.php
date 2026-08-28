<?php

namespace App\Domain\Certificate\Models;

use App\Domain\Certificate\Enums\CertificateStatus;
use App\Domain\Certificate\Enums\CertificateType;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\FanabeDocument;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use App\Domain\School\Models\School;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    use BelongsToTenant, HasVersion4Uuids;

    protected $fillable = [
        'school_id',
        'document_id',
        'subject_person_id',
        'enrollment_id',
        'type',
        'public_reference',
        'issued_at',
        'expires_at',
        'status',
        'revoked_at',
        'revocation_reason',
        'template_version',
        'payload_snapshot',
        'artifact_sha256',
        'signer_key_id',
        'signature',
    ];

    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'status' => CertificateStatus::class,
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'payload_snapshot' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FanabeDocument::class, 'document_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'subject_person_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(CertificateVerificationToken::class);
    }

    public function effectiveStatus(): CertificateStatus
    {
        if ($this->status === CertificateStatus::Revoked) {
            return CertificateStatus::Revoked;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return CertificateStatus::Expired;
        }

        return CertificateStatus::Valid;
    }
}
