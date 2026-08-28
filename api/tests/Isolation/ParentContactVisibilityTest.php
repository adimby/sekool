<?php

use App\Domain\Identity\Actions\RequestPersonLinkByPublicId;
use App\Domain\Identity\Models\UserAccount;

it('hides parent contacts from a school until a link is established', function () {
    $a = $this->provisionSchool();
    $parent = UserAccount::factory()->create([
        'email' => 'hidden.parent@fanabe.test',
    ]);
    $parent->person->forceFill([
        'phone_e164' => '+261341234567',
        'email' => 'hidden.parent@fanabe.test',
    ])->save();

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/people/{$parent->person_id}")
        ->assertNotFound();

    $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$a['school']->id}/person-link-requests", [
            'public_id' => $parent->person->publicIdFormatted(),
        ])
        ->assertAccepted()
        ->assertJsonPath('message', RequestPersonLinkByPublicId::UNIFORM_MESSAGE);

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/people/{$parent->person_id}")
        ->assertNotFound();

    $requestId = $this->actingAs($parent, 'sanctum')
        ->getJson('/api/v1/parent/link-requests')
        ->assertOk()
        ->json('data.0.id');

    $this->actingAs($parent, 'sanctum')
        ->postJson("/api/v1/parent/link-requests/{$requestId}/approve")
        ->assertOk();

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/people/{$parent->person_id}")
        ->assertOk()
        ->assertJsonPath('data.phone_e164', '+261341234567')
        ->assertJsonPath('data.email', 'hidden.parent@fanabe.test');
});
