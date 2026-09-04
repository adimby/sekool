<?php

namespace App\Domain\School\Actions;

use App\Domain\Identity\Actions\AcquirePersonRole;
use App\Domain\Identity\Actions\GrantSchoolPersonLink;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolRoleAssignment;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GrantSchoolAdminFromPlatform
{
    /**
     * @param  array{first_name: string, last_name: string, email: string, password?: string}  $admin
     * @return array{account: UserAccount, temporary_password?: string}
     */
    public function execute(School $school, array $admin): array
    {
        $email = Str::lower(trim($admin['email']));

        return TenantContext::runWithRlsBypass(function () use ($school, $admin, $email): array {
            if (UserAccount::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Cet email a déjà un compte FANABE.',
                ]);
            }

            $person = Person::query()->whereRaw('lower(email) = ?', [$email])->first()
                ?? Person::createWithUniquePublicId([
                    'first_name' => $admin['first_name'],
                    'last_name' => $admin['last_name'],
                    'email' => $email,
                ]);

            $plaintext = $admin['password'] ?? Str::password(12);
            $generated = ! isset($admin['password']);

            $account = UserAccount::query()->create([
                'person_id' => $person->id,
                'email' => $email,
                'password' => $plaintext,
                'must_change_password' => $generated,
            ]);

            $exists = SchoolRoleAssignment::query()
                ->withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('person_id', $account->person_id)
                ->where('role', SchoolRole::Admin)
                ->whereNull('revoked_at')
                ->exists();

            if (! $exists) {
                SchoolRoleAssignment::query()->withoutGlobalScopes()->create([
                    'school_id' => $school->id,
                    'person_id' => $account->person_id,
                    'role' => SchoolRole::Admin,
                    'granted_at' => now(),
                    'granted_by_person_id' => TenantContext::current()?->personId,
                ]);
            }

            app(AcquirePersonRole::class)->execute($account->person_id, PersonRoleType::Staff);
            app(GrantSchoolPersonLink::class)->execute(
                $school->id,
                $account->person_id,
                SchoolPersonLinkKind::Staff,
                SchoolPersonLinkSource::Created,
                false,
            );

            Auditor::record(
                'platform.school.admin.granted',
                'school',
                $school->id,
                $account->person_id,
                context: ['email' => $email, 'role' => SchoolRole::Admin->value],
            );

            $result = ['account' => $account];
            if ($generated) {
                $result['temporary_password'] = $plaintext;
            }

            return $result;
        });
    }
}
