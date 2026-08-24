<?php

use App\Domain\Identity\Actions\MergePersons;
use App\Domain\Identity\Models\Person;

it('keeps both public ids resolvable after a merge', function () {
    $surviving = Person::factory()->create(['first_name' => 'Soa']);
    $duplicate = Person::factory()->create(['first_name' => 'Soa']);
    $survivingPublic = $surviving->public_id;
    $duplicatePublic = $duplicate->public_id;

    app(MergePersons::class)->execute($surviving->id, $duplicate->id, $surviving->id);

    expect(Person::findByPublicId($survivingPublic)->id)->toBe($surviving->id)
        ->and(Person::findByPublicId($duplicatePublic)->id)->toBe($surviving->id)
        ->and($duplicate->fresh()->merged_into_person_id)->toBe($surviving->id)
        ->and($duplicate->fresh()->public_id)->toBe($duplicatePublic);
});
