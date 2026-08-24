<?php

namespace App\Domain\Enrollment\Models;

use App\Domain\Enrollment\Enums\TransferStatus;
use App\Domain\Identity\Models\Person;
use App\Domain\School\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentTransfer extends Model
{
    use HasUuids;

    protected $fillable = [
        'person_id',
        'origin_school_id',
        'origin_enrollment_id',
        'destination_school_id',
        'destination_enrollment_id',
        'requested_by_person_id',
        'parent_approved_at',
        'parent_approved_by_person_id',
        'origin_school_approved_at',
        'origin_approved_by_person_id',
        'status',
        'completed_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'parent_approved_at' => 'datetime',
            'origin_school_approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function originSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'origin_school_id');
    }

    public function destinationSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'destination_school_id');
    }

    public function bothApprovalsPresent(): bool
    {
        return $this->parent_approved_at !== null && $this->origin_school_approved_at !== null;
    }

    public function scopeVisibleToSchool(Builder $query, string $schoolId): Builder
    {
        return $query->where(function (Builder $inner) use ($schoolId): void {
            $inner->where('origin_school_id', $schoolId)
                ->orWhere('destination_school_id', $schoolId);
        });
    }
}
