<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class MergePersons
{
    public function execute(string $survivingPersonId, string $duplicatePersonId, string $actorPersonId): Person
    {
        if ($survivingPersonId === $duplicatePersonId) {
            throw new DomainException('Les deux identités sont identiques.');
        }

        return TenantContext::runWithRlsBypass(function () use ($survivingPersonId, $duplicatePersonId, $actorPersonId): Person {
            return DB::transaction(function () use ($survivingPersonId, $duplicatePersonId, $actorPersonId): Person {
                $surviving = Person::query()->findOrFail($survivingPersonId);
                $duplicate = Person::query()->findOrFail($duplicatePersonId);

                if ($duplicate->merged_into_person_id !== null) {
                    throw new DomainException('Cette identité a déjà été fusionnée.');
                }

                $duplicate->forceFill(['merged_into_person_id' => $survivingPersonId])->save();

                Auditor::record('person.merged', 'person', $surviving->id, $surviving->id, [
                    'duplicate_person_id' => $duplicatePersonId,
                    'duplicate_public_id' => $duplicate->public_id,
                    'actor_person_id' => $actorPersonId,
                ]);

                return $surviving;
            });
        });
    }
}
