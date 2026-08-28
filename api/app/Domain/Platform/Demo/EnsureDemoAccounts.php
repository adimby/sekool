<?php

namespace App\Domain\Platform\Demo;

use App\Domain\Identity\Actions\AcquirePersonRole;
use App\Domain\Identity\Actions\GrantSchoolPersonLink;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolRoleAssignment;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Support\Facades\Hash;

final class EnsureDemoAccounts
{
    /**
     * @var list<array<string, mixed>>
     */
    private const ACCOUNTS = [
        [
            'email' => 'direction.antsahabe@fanabe.test',
            'first_name' => 'Direction',
            'last_name' => 'Antsahabe',
            'school' => 'antsahabe',
            'school_name' => 'École Antsahabe',
            'plan' => 'plus',
            'role' => SchoolRole::Admin,
        ],
        [
            'email' => 'direction.ambohipo@fanabe.test',
            'first_name' => 'Direction',
            'last_name' => 'Ambohipo',
            'school' => 'ambohipo',
            'school_name' => 'École Ambohipo',
            'plan' => 'starter',
            'role' => SchoolRole::Admin,
        ],
        [
            'email' => 'direction.itaosy@fanabe.test',
            'first_name' => 'Direction',
            'last_name' => 'Itaosy',
            'school' => 'itaosy',
            'school_name' => 'École Itaosy',
            'plan' => 'starter',
            'role' => SchoolRole::Admin,
        ],
        [
            'email' => 'teacher.antsahabe@fanabe.test',
            'first_name' => 'Nivo',
            'last_name' => 'Andriamihaja',
            'school' => 'antsahabe',
            'school_name' => 'École Antsahabe',
            'plan' => 'plus',
            'role' => SchoolRole::Teacher,
        ],
        [
            'email' => 'parent.andry@fanabe.test',
            'first_name' => 'Andry',
            'last_name' => 'Rasoanaivo',
        ],
        [
            'email' => 'parent.d@fanabe.test',
            'first_name' => 'Mialy',
            'last_name' => 'Rakoto',
        ],
        [
            'email' => 'eleve.fanja@fanabe.test',
            'first_name' => 'Fanja',
            'last_name' => 'Rakoto',
            'kind' => 'student',
        ],
    ];

    public function execute(): void
    {
        TenantContext::runWithRlsBypass(function (): void {
            foreach (self::ACCOUNTS as $row) {
                $this->ensure($row);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function ensure(array $row): void
    {
        $password = Hash::driver('bcrypt')->make('password');

        if (($row['kind'] ?? null) === 'student') {
            $this->ensureStudent($row, $password);

            return;
        }

        $account = UserAccount::query()->whereRaw('lower(email) = ?', [strtolower($row['email'])])->first();

        if ($account === null) {
            $person = Person::query()->whereRaw('lower(email) = ?', [strtolower($row['email'])])->first()
                ?? Person::createWithUniquePublicId([
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                ]);

            $account = UserAccount::query()->create([
                'person_id' => $person->id,
                'email' => $row['email'],
                'password' => $password,
                'must_change_password' => false,
            ]);
        } else {
            $account->forceFill(['password' => $password])->save();
        }

        if (! isset($row['school'], $row['school_name'])) {
            return;
        }

        $school = School::query()->firstOrCreate(
            ['code' => $row['school']],
            [
                'name' => $row['school_name'],
                'short_name' => explode(' ', $row['school_name'])[1] ?? $row['school_name'],
                'city' => 'Antananarivo',
                'region' => 'Analamanga',
                'plan' => $row['plan'] ?? 'starter',
                'status' => 'active',
            ],
        );

        SchoolYear::query()->firstOrCreate(
            ['school_id' => $school->id, 'label' => '2026-2027'],
            [
                'starts_on' => '2026-09-01',
                'ends_on' => '2027-07-15',
                'is_current' => true,
            ],
        );

        $role = $row['role'] ?? SchoolRole::Admin;
        $exists = SchoolRoleAssignment::query()
            ->where('school_id', $school->id)
            ->where('person_id', $account->person_id)
            ->where('role', $role)
            ->whereNull('revoked_at')
            ->exists();

        if (! $exists) {
            SchoolRoleAssignment::query()->create([
                'school_id' => $school->id,
                'person_id' => $account->person_id,
                'role' => $role,
                'granted_at' => now(),
            ]);
        }

        if ($role === SchoolRole::Teacher) {
            app(AcquirePersonRole::class)->execute($account->person_id, PersonRoleType::Staff);
            app(GrantSchoolPersonLink::class)->execute(
                $school->id,
                $account->person_id,
                SchoolPersonLinkKind::Staff,
                SchoolPersonLinkSource::Created,
                false,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function ensureStudent(array $row, string $password): void
    {
        $person = Person::query()
            ->where('first_name', $row['first_name'])
            ->where('last_name', $row['last_name'])
            ->first();

        if ($person === null) {
            return;
        }

        $account = UserAccount::query()->whereRaw('lower(email) = ?', [strtolower($row['email'])])->first();

        if ($account === null) {
            UserAccount::query()->create([
                'person_id' => $person->id,
                'email' => $row['email'],
                'password' => $password,
                'must_change_password' => false,
            ]);

            return;
        }

        $account->forceFill([
            'person_id' => $person->id,
            'password' => $password,
        ])->save();
    }
}
