<?php

use App\Domain\Identity\Models\FanabeDocument;
use App\Domain\Platform\Tenancy\TenantContext;

it('keeps an external document tagged as external forever', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);

    TenantContext::activate(TenantContext::forSchool($school['school']->id, $school['account']->person_id));

    $document = FanabeDocument::query()->create([
        'school_id' => $school['school']->id,
        'owner_person_id' => $family['student']->id,
        'type' => 'report_card',
        'source_type' => 'external',
        'source_school_label' => 'Lycée Saint-Michel',
        'verification_status' => 'unverified',
        'uploaded_by_person_id' => $family['parent']->id,
        'uploaded_at' => now(),
    ]);

    $document->forceFill([
        'source_type' => 'native',
        'verification_status' => 'attested_by_school',
        'issuer_school_id' => $school['school']->id,
    ])->save();

    $fresh = $document->fresh();

    expect($fresh->source_type)->toBe('external')
        ->and($fresh->isExternal())->toBeTrue()
        ->and($fresh->isNative())->toBeFalse()
        ->and($fresh->source_school_label)->toBe('Lycée Saint-Michel')
        ->and($fresh->verification_status)->toBe('attested_by_school');

    TenantContext::clear();
});
