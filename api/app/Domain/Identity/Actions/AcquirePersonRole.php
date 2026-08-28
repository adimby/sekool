<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Models\PersonRole;

final class AcquirePersonRole
{
    public function execute(string $personId, PersonRoleType $role, ?\DateTimeInterface $acquiredAt = null): PersonRole
    {
        $open = PersonRole::query()
            ->where('person_id', $personId)
            ->where('role', $role)
            ->whereNull('ended_at')
            ->first();

        if ($open !== null) {
            return $open;
        }

        return PersonRole::query()->create([
            'person_id' => $personId,
            'role' => $role,
            'acquired_at' => $acquiredAt ?? now(),
        ]);
    }

    public function close(string $personId, PersonRoleType $role, ?\DateTimeInterface $endedAt = null): void
    {
        PersonRole::query()
            ->where('person_id', $personId)
            ->where('role', $role)
            ->whereNull('ended_at')
            ->update(['ended_at' => $endedAt ?? now()]);
    }
}
