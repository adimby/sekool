<?php

use App\Domain\Certificate\Models\Certificate;
use App\Domain\Certificate\Models\CertificateVerificationToken;
use App\Domain\Certificate\Support\CertificateCopy;
use App\Domain\Identity\Models\FanabeDocument;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

it('issues an enrollment certificate whose public verify masks the name', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ])->assertOk();

    $issued = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/certificates")
        ->assertCreated()
        ->json('data');

    expect($issued['token'])->toHaveLength(40)
        ->and($issued['verify_url'])->toEndWith('/verify/'.$issued['token'])
        ->and($issued['disclaimer'])->toBe(CertificateCopy::DISCLAIMER);

    TenantContext::runWithRlsBypass(function () use ($issued): void {
        expect(CertificateVerificationToken::query()->withoutGlobalScopes()->where('token_hash', $issued['token'])->exists())->toBeFalse()
            ->and(CertificateVerificationToken::query()->withoutGlobalScopes()->where('token_hash', hash('sha256', $issued['token']))->exists())->toBeTrue();

        $certificate = Certificate::query()->withoutGlobalScopes()->find($issued['id']);
        $stored = Storage::disk('local')->get('schools/'.$certificate->school_id.'/certificates/'.$certificate->public_reference.'.html');
        expect($certificate->artifact_sha256)->toBe(hash('sha256', $stored))
            ->and($stored)->toContain(CertificateCopy::DISCLAIMER);
    });

    $verify = $this->getJson('/api/v1/verify/certificates/'.$issued['token'])
        ->assertOk()
        ->json();

    expect($verify['status'])->toBe('VALID')
        ->and($verify['person'])->toBe('Fanja R.')
        ->and($verify['full_name_revealed'])->toBeFalse()
        ->and($verify)->not->toHaveKey('public_id')
        ->and(json_encode($verify))->not->toContain($family['student']->public_id)
        ->and($verify['disclaimer'])->toBe(CertificateCopy::DISCLAIMER);

    $revealed = $this->getJson('/api/v1/verify/certificates/'.$issued['token'].'?birth_date=2013-04-02')
        ->assertOk()
        ->json();

    expect($revealed['person'])->toBe('Fanja Rakoto')
        ->and($revealed['full_name_revealed'])->toBeTrue();

    $this->get('/verify/'.$issued['token'])
        ->assertOk()
        ->assertSee('Fanja R.', false)
        ->assertSee(CertificateCopy::DISCLAIMER, false);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/certificates/{$issued['id']}/revoke", [
            'reason' => 'Erreur de classe',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked');

    $this->getJson('/api/v1/verify/certificates/'.$issued['token'])
        ->assertOk()
        ->assertJsonPath('status', 'REVOKED');

    $this->getJson('/api/v1/verify/certificates/'.bin2hex(random_bytes(20)))
        ->assertOk()
        ->assertJsonPath('status', 'UNKNOWN')
        ->assertJsonMissingPath('issuer');
});

it('keeps an attested external document tagged as external', function () {
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
    TenantContext::clear();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/documents/{$document->id}/attest")
        ->assertOk()
        ->assertJsonPath('data.source_type', 'external')
        ->assertJsonPath('data.verification_status', 'attested_by_school');
});

it('throttles public certificate verification', function () {
    $route = collect(Route::getRoutes())->first(
        fn ($route): bool => $route->uri() === 'api/v1/verify/certificates/{token}',
    );

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:certificate-verify');
});
