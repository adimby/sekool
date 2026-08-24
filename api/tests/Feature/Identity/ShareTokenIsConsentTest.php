<?php

use App\Domain\Identity\Actions\GenerateFamilyShareToken;
use App\Domain\Identity\Actions\RequestPersonLinkByPublicId;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Platform\Tenancy\TenantContext;

it('treats a parent share token as consumable consent, not a public id', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $other = $this->provisionSchool();

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    $issued = app(GenerateFamilyShareToken::class)->execute($family['parent']->id, [$family['student']->id]);
    TenantContext::clear();

    $this->actingAs($other['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$other['school']->id}/share-tokens/redeem", ['token' => $issued['token']])
        ->assertOk()
        ->assertJsonFragment(['id' => $family['parent']->id]);

    $link = TenantContext::runWithRlsBypass(fn () => SchoolPersonLink::query()
        ->withoutGlobalScopes()
        ->where('school_id', $other['school']->id)
        ->where('person_id', $family['parent']->id)
        ->first());

    expect($link)->not->toBeNull()
        ->and($link->grants_contact_access)->toBeTrue()
        ->and($link->source->value)->toBe('share_token');

    $this->actingAs($other['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$other['school']->id}/share-tokens/redeem", ['token' => $issued['token']])
        ->assertStatus(422);

    $this->actingAs($other['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$other['school']->id}/people/{$family['parent']->id}")
        ->assertOk()
        ->assertJsonPath('data.phone_e164', $family['parent']->phone_e164);
});

it('does not grant contact access when a public id is merely submitted', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $other = $this->provisionSchool();

    $this->actingAs($other['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$other['school']->id}/person-link-requests", [
            'public_id' => $family['parent']->publicIdFormatted(),
        ])
        ->assertAccepted()
        ->assertJsonPath('message', RequestPersonLinkByPublicId::UNIFORM_MESSAGE);

    $this->actingAs($other['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$other['school']->id}/people/{$family['parent']->id}")
        ->assertNotFound();
});
