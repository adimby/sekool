<?php

namespace App\Domain\Academic\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinComment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'school_id',
        'enrollment_id',
        'academic_term_id',
        'subject_id',
        'body',
        'recorded_by_person_id',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'recorded_by_person_id');
    }
}
