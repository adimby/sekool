<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\IdentityMerge;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;

final class RequestIdentityMerge
{
    public function execute(
        string $schoolId,
        string $actorPersonId,
        string $survivingPublicId,
        string $duplicatePublicId,
        string $reason,
    ): IdentityMerge {
        try {
            $surviving = Person::findByPublicId($survivingPublicId);
            $duplicate = Person::findByPublicId($duplicatePublicId);
        } catch (\InvalidArgumentException) {
            throw new DomainException('Identité introuvable.', 404);
        }
        if ($surviving === null || $duplicate === null) {
            throw new DomainException('Identité introuvable.', 404);
        }
        if ($surviving->id === $duplicate->id) {
            throw new DomainException('Les deux identités sont identiques.');
        }

        $linked = SchoolPersonLink::query()
            ->whereIn('person_id', [$surviving->id, $duplicate->id])
            ->pluck('person_id')
            ->unique();
        if ($linked->count() !== 2) {
            throw new DomainException('Identité introuvable.', 404);
        }

        $existing = IdentityMerge::query()
            ->where('duplicate_person_id', $duplicate->id)
            ->whereIn('status', [IdentityMerge::REQUESTED, IdentityMerge::MERGED])
            ->first();
        if ($existing !== null) {
            throw new DomainException('Une fusion est déjà en cours ou effective pour cette identité.');
        }

        $row = IdentityMerge::query()->create([
            'school_id' => $schoolId,
            'surviving_person_id' => $surviving->id,
            'duplicate_person_id' => $duplicate->id,
            'reason' => $reason,
            'requested_by_person_id' => $actorPersonId,
            'status' => IdentityMerge::REQUESTED,
        ]);

        TenantContext::runWithRlsBypass(fn () => Auditor::record(
            'person.merge_requested',
            'person',
            $surviving->id,
            $duplicate->id,
            ['reason' => $reason, 'school_id' => $schoolId],
        ));

        return $row->fresh(['surviving', 'duplicate']) ?? $row;
    }
}
