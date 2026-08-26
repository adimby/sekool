<?php

namespace App\Domain\Platform\Demo;

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
     * @var list<array{email: string, first_name: string, last_name: string, school?: string, school_name?: string, plan?: string}>
     */
    private const ACCOUNTS = [
        [
            'email' => 'direction.antsahabe@fanabe.test',
            'first_name' => 'Direction',
            'last_name' => 'Antsahabe',
            'school' => 'antsahabe',
            'school_name' => 'École Antsahabe',
            'plan' => 'plus',
        ],
        [
            'email' => 'direction.ambohipo@fanabe.test',
            'first_name' => 'Direction',
            'last_name' => 'Ambohipo',
            'school' => 'ambohipo',
            'school_name' => 'École Ambohipo',
            'plan' => 'starter',
        ],
        [
            'email' => 'direction.itaosy@fanabe.test',
            'first_name' => 'Direction',
            'last_name' => 'Itaosy',
            'school' => 'itaosy',
            'school_name' => 'École Itaosy',
            'plan' => 'starter',
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
     * @param  array{email: string, first_name: string, last_name: string, school?: string, school_name?: string, plan?: string}  $row
     */
    private function ensure(array $row): void
    {
        $password = Hash::driver('bcrypt')->make('password');

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

        $exists = SchoolRoleAssignment::query()
            ->where('school_id', $school->id)
            ->where('person_id', $account->person_id)
            ->where('role', SchoolRole::Admin)
            ->whereNull('revoked_at')
            ->exists();

        if (! $exists) {
            SchoolRoleAssignment::query()->create([
                'school_id' => $school->id,
                'person_id' => $account->person_id,
                'role' => SchoolRole::Admin,
                'granted_at' => now(),
            ]);
        }
    }
}
