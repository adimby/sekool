<?php

namespace Tests;

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolRoleAssignment;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * @return array{school: School, account: UserAccount, year: SchoolYear}
     */
    protected function provisionSchool(?SchoolRole $role = SchoolRole::Admin): array
    {
        TenantContext::activate(TenantContext::migrationBypass());

        $school = School::factory()->create();
        $account = UserAccount::factory()->create();

        SchoolRoleAssignment::query()->create([
            'school_id' => $school->id,
            'person_id' => $account->person_id,
            'role' => $role,
            'granted_at' => now(),
        ]);

        $year = SchoolYear::factory()->create([
            'school_id' => $school->id,
            'label' => '2026-2027',
        ]);

        TenantContext::clear();

        return compact('school', 'account', 'year');
    }

    protected function actingAsSchool(UserAccount $account, School $school): static
    {
        TenantContext::activate(TenantContext::forSchool($school->id, $account->person_id));

        return $this->actingAs($account);
    }
}
