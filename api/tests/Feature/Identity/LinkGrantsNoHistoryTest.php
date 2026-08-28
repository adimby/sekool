<?php

use App\Domain\Enrollment\Actions\EnrollStudent;
use App\Domain\Identity\Actions\GenerateFamilyShareToken;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;

it('does not open another school\'s history when a share token is redeemed', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    $share = app(GenerateFamilyShareToken::class)->execute($family['parent']->id, [$family['student']->id]);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$b['school']->id}/share-tokens/redeem", [
            'token' => $share['token'],
        ])
        ->assertOk();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/people/{$family['student']->id}/academic-history")
        ->assertOk()
        ->assertJsonCount(0, 'data.shared');

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/enrollments")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('does not treat a public id as permission to enroll', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);

    $this->actingAs($b['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$b['school']->id}/person-link-requests", [
            'public_id' => $family['student']->publicIdFormatted(),
        ])
        ->assertAccepted();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));

    expect(fn () => app(EnrollStudent::class)->execute(
        $b['school']->id,
        $b['year']->id,
        $family['student']->id,
        $b['account']->person_id,
    ))->toThrow(DomainException::class);
});
