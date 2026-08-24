<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

class FanabeDocument extends Model
{
    use HasVersion4Uuids;

    protected $table = 'documents';

    protected $fillable = [
        'school_id',
        'owner_person_id',
        'type',
        'source_type',
        'source_school_label',
        'issuer_school_id',
        'verification_status',
        'uploaded_by_person_id',
        'uploaded_at',
        'storage_key',
        'sha256',
        'byte_size',
        'mime_type',
        'version',
        'provenance',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'provenance' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            $original = $document->getOriginal('source_type');

            if ($original === 'external') {
                $document->source_type = 'external';
            }
        });
    }

    public function isExternal(): bool
    {
        return $this->source_type === 'external';
    }

    public function isNative(): bool
    {
        return $this->source_type === 'native';
    }
}
