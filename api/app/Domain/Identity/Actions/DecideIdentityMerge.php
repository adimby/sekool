<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\IdentityMerge;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;

final class DecideIdentityMerge
{
    public function __construct(private readonly MergePersons $merge) {}

    public function approve(string $mergeId, string $actorPersonId): IdentityMerge
    {
        return TenantContext::runWithRlsBypass(function () use ($mergeId, $actorPersonId): IdentityMerge {
            $row = IdentityMerge::query()->withoutGlobalScopes()->with(['surviving', 'duplicate'])->find($mergeId);
            if ($row === null || $row->status !== IdentityMerge::REQUESTED) {
                throw new DomainException('Demande de fusion introuvable.', 404);
            }

            $this->merge->execute($row->surviving_person_id, $row->duplicate_person_id, $actorPersonId);
            $row->forceFill([
                'status' => IdentityMerge::MERGED,
                'decided_by_person_id' => $actorPersonId,
                'decided_at' => now(),
            ])->save();

            Auditor::record('person.merge_approved', 'person', $row->surviving_person_id, $row->duplicate_person_id);

            return $row->fresh(['surviving', 'duplicate']) ?? $row;
        });
    }

    public function refuse(string $mergeId, string $actorPersonId): IdentityMerge
    {
        return TenantContext::runWithRlsBypass(function () use ($mergeId, $actorPersonId): IdentityMerge {
            $row = IdentityMerge::query()->withoutGlobalScopes()->find($mergeId);
            if ($row === null || $row->status !== IdentityMerge::REQUESTED) {
                throw new DomainException('Demande de fusion introuvable.', 404);
            }

            $row->forceFill([
                'status' => IdentityMerge::REFUSED,
                'decided_by_person_id' => $actorPersonId,
                'decided_at' => now(),
            ])->save();

            Auditor::record('person.merge_refused', 'person', $row->surviving_person_id, $row->duplicate_person_id);

            return $row->fresh(['surviving', 'duplicate']) ?? $row;
        });
    }

    public function undo(string $mergeId, string $actorPersonId): IdentityMerge
    {
        return TenantContext::runWithRlsBypass(function () use ($mergeId, $actorPersonId): IdentityMerge {
            $row = IdentityMerge::query()->withoutGlobalScopes()->with('duplicate')->find($mergeId);
            if ($row === null || $row->status !== IdentityMerge::MERGED) {
                throw new DomainException('Fusion introuvable.', 404);
            }

            $duplicate = $row->duplicate;
            if ($duplicate === null || $duplicate->merged_into_person_id !== $row->surviving_person_id) {
                throw new DomainException('Cette fusion ne peut plus être défaite.');
            }

            $duplicate->forceFill(['merged_into_person_id' => null])->save();
            $row->forceFill([
                'status' => IdentityMerge::UNDONE,
                'decided_by_person_id' => $actorPersonId,
                'decided_at' => now(),
            ])->save();

            Auditor::record('person.merge_undone', 'person', $row->surviving_person_id, $row->duplicate_person_id);

            return $row->fresh(['surviving', 'duplicate']) ?? $row;
        });
    }
}
