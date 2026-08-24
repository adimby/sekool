<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolRoleAssignment;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        TenantContext::activate(TenantContext::migrationBypass());

        $this->seedSchool(
            name: 'École Antsahabe',
            code: 'antsahabe',
            city: 'Antananarivo',
            plan: 'plus',
            adminEmail: 'direction.antsahabe@fanabe.test',
        );

        $this->seedSchool(
            name: 'École Ambohipo',
            code: 'ambohipo',
            city: 'Antananarivo',
            plan: 'starter',
            adminEmail: 'direction.ambohipo@fanabe.test',
        );

        $this->seedSchool(
            name: 'École Itaosy',
            code: 'itaosy',
            city: 'Antananarivo',
            plan: 'starter',
            adminEmail: 'direction.itaosy@fanabe.test',
        );

        $this->call(PersonaSeeder::class);

        TenantContext::clear();
    }

    private function seedSchool(string $name, string $code, string $city, string $plan, string $adminEmail): void
    {
        $school = School::query()->create([
            'name' => $name,
            'short_name' => explode(' ', $name)[1] ?? $name,
            'code' => $code,
            'city' => $city,
            'region' => 'Analamanga',
            'plan' => $plan,
            'status' => 'active',
        ]);

        $account = UserAccount::factory()->create([
            'email' => $adminEmail,
            'password' => 'password',
        ]);

        SchoolRoleAssignment::query()->create([
            'school_id' => $school->id,
            'person_id' => $account->person_id,
            'role' => SchoolRole::Admin,
            'granted_at' => now(),
        ]);

        SchoolYear::query()->create([
            'school_id' => $school->id,
            'label' => '2026-2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-07-15',
            'is_current' => true,
        ]);
    }
}
