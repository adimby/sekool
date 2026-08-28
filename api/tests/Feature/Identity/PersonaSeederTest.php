<?php

use App\Domain\Identity\Models\ExternalEducationPeriod;
use App\Domain\Identity\Models\FanabeDocument;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;

it('seeds personas A, B and C with a single portable identity each', function () {
    $this->seed(DatabaseSeeder::class);

    $andry = Person::query()->where('email', 'parent.andry@fanabe.test')->first();
    $fanja = Person::query()->where('first_name', 'Fanja')->where('last_name', 'Rakoto')->first();
    $tojo = Person::query()->where('first_name', 'Tojo')->where('last_name', 'Andrianina')->first();

    expect($andry)->not->toBeNull()
        ->and($andry->roles()->count())->toBeGreaterThanOrEqual(3)
        ->and($fanja)->not->toBeNull()
        ->and($tojo)->not->toBeNull();

    $external = ExternalEducationPeriod::query()->where('person_id', $tojo->id)->first();
    $bulletin = TenantContext::runWithRlsBypass(fn () => FanabeDocument::query()->where('owner_person_id', $tojo->id)->first());

    expect($external?->school_label)->toBe('Lycée Saint-Michel')
        ->and($bulletin?->source_type)->toBe('external')
        ->and($bulletin?->verification_status)->toBe('attested_by_school');
});
