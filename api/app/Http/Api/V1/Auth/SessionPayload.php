<?php

namespace App\Http\Api\V1\Auth;

use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Models\PersonRole;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Identity\Support\PersonPayload;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\SchoolRoleAssignment;

final class SessionPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function for(UserAccount $account, string $token): array
    {
        $account->loadMissing('person');

        $schools = TenantContext::runWithRlsBypass(fn () => SchoolRoleAssignment::query()
            ->withoutGlobalScopes()
            ->where('person_id', $account->person_id)
            ->whereNull('revoked_at')
            ->with('school')
            ->get()
            ->map(fn (SchoolRoleAssignment $assignment): array => [
                'id' => $assignment->school_id,
                'name' => $assignment->school?->name,
                'code' => $assignment->school?->code,
                'role' => $assignment->role->value,
            ])
            ->values());

        $isParent = PersonRole::query()
            ->where('person_id', $account->person_id)
            ->where('role', PersonRoleType::Parent)
            ->whereNull('ended_at')
            ->exists();

        return [
            'token' => $token,
            'person_id' => $account->person_id,
            'person' => PersonPayload::forParent($account->person),
            'schools' => $schools,
            'is_parent' => $isParent,
        ];
    }
}
