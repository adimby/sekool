<?php

namespace App\Http\Api\V1\Auth;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Models\PersonRole;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Identity\Support\PersonPayload;
use App\Domain\Identity\Support\PrivilegedAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\SchoolRoleAssignment;
use App\Domain\School\Support\SchoolCapabilities;
use Illuminate\Support\Collection;

final class SessionPayload
{
    /**
     * @var list<string>
     */
    private const PRIMARY_ROLE_ORDER = [
        SchoolRole::Owner->value,
        SchoolRole::Admin->value,
        SchoolRole::Principal->value,
        SchoolRole::Accountant->value,
        SchoolRole::Teacher->value,
        SchoolRole::Staff->value,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function for(UserAccount $account, string $token): array
    {
        $account->loadMissing('person');

        [$assignments, $titulaireSchoolIds, $enseigneSchoolIds] = TenantContext::runWithRlsBypass(function () use ($account): array {
            $assignments = SchoolRoleAssignment::query()
                ->withoutGlobalScopes()
                ->where('person_id', $account->person_id)
                ->whereNull('revoked_at')
                ->with('school')
                ->get();

            $personId = $account->person_id;

            $titulaireSchoolIds = Classroom::query()
                ->withoutGlobalScopes()
                ->where('main_teacher_person_id', $personId)
                ->pluck('school_id')
                ->unique()
                ->values()
                ->all();

            $enseigneSchoolIds = Classroom::query()
                ->withoutGlobalScopes()
                ->where(function ($query) use ($personId): void {
                    $query->where('main_teacher_person_id', $personId)
                        ->orWhereHas('teachers', fn ($teachers) => $teachers->where('person_id', $personId))
                        ->orWhereHas('timetableSlots', fn ($slots) => $slots->where('teacher_person_id', $personId));
                })
                ->pluck('school_id')
                ->unique()
                ->values()
                ->all();

            return [$assignments, $titulaireSchoolIds, $enseigneSchoolIds];
        });

        $schools = $assignments
            ->groupBy('school_id')
            ->map(function (Collection $rows) use ($titulaireSchoolIds, $enseigneSchoolIds): array {
                $assignment = $rows->first();
                $roles = $rows
                    ->map(fn (SchoolRoleAssignment $row): string => $row->role->value)
                    ->unique()
                    ->values()
                    ->all();
                $titulaire = in_array($assignment->school_id, $titulaireSchoolIds, true);
                $enseigne = in_array($assignment->school_id, $enseigneSchoolIds, true);

                return [
                    'id' => $assignment->school_id,
                    'name' => $assignment->school?->name,
                    'code' => $assignment->school?->code,
                    'role' => self::primaryRole($roles),
                    'roles' => $roles,
                    'titulaire' => $titulaire,
                    'enseigne' => $enseigne,
                    'capabilities' => SchoolCapabilities::for($roles, $titulaire, $enseigne),
                ];
            })
            ->values();

        $openRoles = PersonRole::query()
            ->where('person_id', $account->person_id)
            ->whereNull('ended_at')
            ->pluck('role');

        return [
            'token' => $token,
            'person_id' => $account->person_id,
            'person' => PersonPayload::forParent($account->person),
            'schools' => $schools,
            'is_parent' => $openRoles->contains(function (mixed $role): bool {
                $value = $role instanceof PersonRoleType ? $role->value : (string) $role;

                return in_array($value, [
                    PersonRoleType::Parent->value,
                    PersonRoleType::Guardian->value,
                    PersonRoleType::FinancialContact->value,
                ], true);
            }),
            'is_guardian' => $openRoles->contains(function (mixed $role): bool {
                $value = $role instanceof PersonRoleType ? $role->value : (string) $role;

                return in_array($value, [
                    PersonRoleType::Parent->value,
                    PersonRoleType::Guardian->value,
                ], true);
            }),
            'is_student' => $openRoles->contains(fn (mixed $role): bool => $role === PersonRoleType::Student || $role === PersonRoleType::Student->value),
            'is_platform_admin' => PrivilegedAccount::isPlatformAdmin($account),
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    private static function primaryRole(array $roles): string
    {
        foreach (self::PRIMARY_ROLE_ORDER as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return $roles[0] ?? SchoolRole::Staff->value;
    }
}
