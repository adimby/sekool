<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Models\SchoolPersonLink;

final class GrantSchoolPersonLink
{
    public function execute(
        string $schoolId,
        string $personId,
        SchoolPersonLinkKind $kind,
        SchoolPersonLinkSource $source,
        bool $grantsContactAccess,
    ): SchoolPersonLink {
        $existing = SchoolPersonLink::query()
            ->where('school_id', $schoolId)
            ->where('person_id', $personId)
            ->where('kind', $kind)
            ->first();

        if ($existing !== null) {
            if ($grantsContactAccess && ! $existing->grants_contact_access) {
                $existing->forceFill(['grants_contact_access' => true])->save();
            }

            return $existing->refresh();
        }

        return SchoolPersonLink::query()->create([
            'school_id' => $schoolId,
            'person_id' => $personId,
            'kind' => $kind,
            'source' => $source,
            'grants_contact_access' => $grantsContactAccess,
            'established_at' => now(),
        ]);
    }
}
