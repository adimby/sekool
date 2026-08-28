<?php

use App\Domain\Consent\Actions\GrantConsent;
use App\Domain\Consent\Actions\RevokeConsent;
use App\Domain\Consent\Enums\ConsentScope;
use App\Domain\Enrollment\Actions\ApproveEnrollmentTransfer;
use App\Domain\Enrollment\Actions\EnrollStudent;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Identity\Actions\GenerateFamilyShareToken;
use App\Domain\Identity\Actions\RedeemFamilyShareToken;
use App\Domain\Platform\Tenancy\TenantContext;

it('does not let school B read school A history without consent', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    $share = app(GenerateFamilyShareToken::class)->execute($family['parent']->id, [$family['student']->id]);
    TenantContext::clear();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    app(RedeemFamilyShareToken::class)->execute($b['school']->id, $b['account']->person_id, $share['token']);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/people/{$family['student']->id}/academic-history")
        ->assertOk()
        ->assertJsonCount(0, 'data.own')
        ->assertJsonCount(0, 'data.shared');
});

it('reveals another school\'s enrollments only while a consent is active', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    $share = app(GenerateFamilyShareToken::class)->execute($family['parent']->id, [$family['student']->id]);
    $consent = app(GrantConsent::class)->execute(
        $family['student']->id,
        $family['parent']->id,
        $b['school']->id,
        ConsentScope::AcademicRecords,
        'Partage du parcours',
    );
    TenantContext::clear();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    app(RedeemFamilyShareToken::class)->execute($b['school']->id, $b['account']->person_id, $share['token']);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/people/{$family['student']->id}/academic-history")
        ->assertOk()
        ->assertJsonCount(1, 'data.shared')
        ->assertJsonPath('data.shared.0.id', $family['enrollment']->id);

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    app(RevokeConsent::class)->execute($consent->id, $family['parent']->id);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/people/{$family['student']->id}/academic-history")
        ->assertOk()
        ->assertJsonCount(0, 'data.shared');
});

it('keeps origin history invisible to the destination after a completed transfer', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    $share = app(GenerateFamilyShareToken::class)->execute($family['parent']->id, [$family['student']->id]);
    TenantContext::clear();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    app(RedeemFamilyShareToken::class)->execute($b['school']->id, $b['account']->person_id, $share['token']);
    $transfer = app(EnrollStudent::class)->execute(
        $b['school']->id,
        $b['year']->id,
        $family['student']->id,
        $b['account']->person_id,
    );
    expect($transfer)->toBeInstanceOf(EnrollmentTransfer::class);
    TenantContext::clear();

    app(ApproveEnrollmentTransfer::class)->byParent($transfer->id, $family['parent']->id);

    TenantContext::activate(TenantContext::forSchool($a['school']->id, $a['account']->person_id));
    app(ApproveEnrollmentTransfer::class)->byOriginSchool($transfer->id, $a['school']->id, $a['account']->person_id);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/people/{$family['student']->id}/academic-history")
        ->assertOk()
        ->assertJsonCount(1, 'data.own')
        ->assertJsonCount(0, 'data.shared');
});
