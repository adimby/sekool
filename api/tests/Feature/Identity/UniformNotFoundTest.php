<?php

use App\Domain\Identity\Actions\RequestPersonLinkByPublicId;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\PublicId\FanabePublicId;

it('returns the same body for an unknown id and an existing unlinked id', function () {
    $school = $this->provisionSchool();
    $stranger = Person::factory()->create();

    $unknown = FanabePublicId::generate()->formatted();

    $missing = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/person-link-requests", [
            'public_id' => $unknown,
        ]);

    $existing = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/person-link-requests", [
            'public_id' => $stranger->publicIdFormatted(),
        ]);

    $missing->assertAccepted()->assertJsonPath('message', RequestPersonLinkByPublicId::UNIFORM_MESSAGE);
    $existing->assertAccepted()->assertJsonPath('message', RequestPersonLinkByPublicId::UNIFORM_MESSAGE);

    expect($missing->json())->toBe($existing->json());
});

it('rejects a malformed public id without probing existence', function () {
    $school = $this->provisionSchool();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/person-link-requests", [
            'public_id' => '7-48372196-K',
        ])
        ->assertStatus(422);
});
