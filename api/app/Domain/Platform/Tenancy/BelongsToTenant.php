<?php

namespace App\Domain\Platform\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent barrier (barrier 2). Combined with RLS (barrier 3) and composite FKs (barrier 4).
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $context = TenantContext::current();

            if ($context?->rlsBypass || $context?->isPlatformAdmin) {
                return;
            }

            $schoolId = $context?->schoolId;

            if ($schoolId === null || $schoolId === '') {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($query->getModel()->qualifyColumn('school_id'), $schoolId);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('school_id') === null) {
                $model->setAttribute('school_id', TenantContext::requireSchoolId());
            }
        });
    }
}
