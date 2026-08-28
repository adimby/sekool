<?php

use App\Domain\Consent\Actions\GrantConsent;
use App\Domain\Consent\Actions\RevokeConsent;
use App\Domain\Consent\Enums\ConsentScope;
use App\Domain\Identity\Actions\GenerateFamilyShareToken;
use App\Domain\Identity\Actions\RedeemFamilyShareToken;
use App\Domain\Platform\Audit\AuditEvent;
use App\Domain\Platform\Tenancy\TenantContext;

it('cuts future reads on revocation and leaves the audit trail untouched', function () {
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
        'Partage',
    );
    TenantContext::clear();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    app(RedeemFamilyShareToken::class)->execute($b['school']->id, $b['account']->person_id, $share['token']);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/people/{$family['student']->id}/academic-history")
        ->assertJsonCount(1, 'data.shared');

    $auditCount = TenantContext::runWithRlsBypass(fn () => AuditEvent::query()->count());

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    $revoked = app(RevokeConsent::class)->execute($consent->id, $family['parent']->id);
    TenantContext::clear();

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/people/{$family['student']->id}/academic-history")
        ->assertJsonCount(0, 'data.shared');

    expect($revoked->revoked_at)->not->toBeNull()
        ->and(TenantContext::runWithRlsBypass(fn () => AuditEvent::query()->count()))->toBeGreaterThanOrEqual($auditCount)
        ->and(TenantContext::runWithRlsBypass(fn () => $revoked->events()->where('event', 'revoked')->exists()))->toBeTrue();
});

it('stops honouring a consent after its expiry without a manual step', function () {
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
        'Partage temporaire',
    );
    TenantContext::clear();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    app(RedeemFamilyShareToken::class)->execute($b['school']->id, $b['account']->person_id, $share['token']);
    TenantContext::clear();

    TenantContext::runWithRlsBypass(fn () => $consent->forceFill(['expires_at' => now()->subMinute()])->save());

    $this->actingAs($b['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/people/{$family['student']->id}/academic-history")
        ->assertOk()
        ->assertJsonCount(0, 'data.shared');
});
