<?php

use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Identity\Support\ParentAuthorization;

it('lets direction edit a family and a child already enrolled', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $schoolId = $school['school']->id;
    $familyId = $family['family']->id;

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/people/{$family['student']->id}", [
            'first_name' => 'Fanja-Marie',
            'last_name' => 'Rakoto',
        ])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Fanja-Marie');

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/families/{$familyId}", [
            'label' => 'Rakoto-Rasoa',
        ])
        ->assertOk()
        ->assertJsonPath('data.label', 'Rakoto-Rasoa');

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/families")
        ->assertOk()
        ->assertJsonFragment(['id' => $familyId]);
});

it('adds a sibling and a second adult to an existing family', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $schoolId = $school['school']->id;
    $familyId = $family['family']->id;

    $sibling = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/families/{$familyId}/children", [
            'school_year_id' => $school['year']->id,
            'first_name' => 'Soa',
            'last_name' => 'Rakoto',
            'birth_date' => '2016-03-12',
        ])
        ->assertCreated()
        ->json('student');

    expect($sibling['first_name'])->toBe('Soa')
        ->and(ParentAuthorization::isLegalGuardianOf($family['parent']->id, $sibling['id']))->toBeTrue();

    $adult = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/families/{$familyId}/adults", [
            'first_name' => 'Rivo',
            'last_name' => 'Rakoto',
            'phone' => '0341112233',
            'relationship' => RelationshipType::FinancialContactFor->value,
        ])
        ->assertCreated()
        ->json();

    expect($adult['invitation_code'])->not->toBeNull();

    $claimed = $this->postJson('/api/v1/auth/invitations/claim', [
        'code' => $adult['invitation_code'],
        'email' => 'rivo.finance@fanabe.test',
        'password' => 'secret-pass',
    ])->assertOk();

    expect($claimed->json('is_parent'))->toBeTrue();

    $account = UserAccount::query()->whereRaw('lower(email) = ?', ['rivo.finance@fanabe.test'])->firstOrFail();

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/parent/children')
        ->assertOk()
        ->assertJsonPath('data.0.access', 'finance');

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/parent/children/'.$family['student']->id.'/finance')
        ->assertOk();

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/parent/children/'.$family['student']->id.'/attendance')
        ->assertNotFound();
});

it('adds a pickup adult without a family portal and lets direction edit them', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $schoolId = $school['school']->id;
    $familyId = $family['family']->id;

    $adult = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/families/{$familyId}/adults", [
            'first_name' => 'Hery',
            'last_name' => 'Rasoa',
            'phone' => '0345556677',
            'relationship' => RelationshipType::PickupAuthorizedFor->value,
        ])
        ->assertCreated()
        ->json();

    expect($adult['invitation_code'])->toBeNull();

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/families/{$familyId}/members/".$adult['adult']['id'], [
            'first_name' => 'Hery-Jean',
            'phone' => '0345556688',
        ])
        ->assertOk()
        ->assertJsonFragment(['first_name' => 'Hery-Jean']);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/families/{$familyId}/invite", [
            'person_id' => $adult['adult']['id'],
        ])
        ->assertUnprocessable();
});

it('hides family edits from a parent and from another school', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->patchJson("/api/v1/schools/{$a['school']->id}/people/{$family['student']->id}", [
            'first_name' => 'Intrus',
        ])
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/families/{$family['family']->id}")
        ->assertNotFound();
});
