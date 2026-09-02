<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\SchoolRoleAssignment;

final class PrivilegedAccount
{
    /**
     * @var list<string>
     */
    public const TOTP_ROLES = [
        SchoolRole::Owner->value,
        SchoolRole::Admin->value,
        SchoolRole::Accountant->value,
    ];

    public static function requiresTotp(UserAccount $account): bool
    {
        $roles = TenantContext::runWithRlsBypass(fn () => SchoolRoleAssignment::query()
            ->withoutGlobalScopes()
            ->where('person_id', $account->person_id)
            ->whereNull('revoked_at')
            ->get()
            ->map(fn (SchoolRoleAssignment $row): string => $row->role instanceof SchoolRole ? $row->role->value : (string) $row->role)
            ->values()
            ->all());

        return collect($roles)->intersect(self::TOTP_ROLES)->isNotEmpty();
    }
}
