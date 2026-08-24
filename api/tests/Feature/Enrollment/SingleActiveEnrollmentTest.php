<?php

use App\Domain\Enrollment\Actions\EnrollStudent;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Identity\Actions\GenerateFamilyShareToken;
use App\Domain\Identity\Actions\RedeemFamilyShareToken;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('allows only one active enrollment per person in the whole network', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));

    expect(function () use ($family, $b): void {
        TenantContext::runWithRlsBypass(function () use ($family, $b): void {
            DB::transaction(function () use ($family, $b): void {
                Enrollment::query()->withoutGlobalScopes()->create([
                    'school_id' => $b['school']->id,
                    'school_year_id' => $b['year']->id,
                    'person_id' => $family['student']->id,
                    'status' => EnrollmentStatus::Active,
                    'enrolled_on' => now()->toDateString(),
                    'source_type' => 'native',
                ]);
            });
        });
    })->toThrow(UniqueConstraintViolationException::class);

    TenantContext::clear();
});

it('opens a transfer instead of a second active enrollment when the destination enrolls', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    $share = app(GenerateFamilyShareToken::class)
        ->execute($family['parent']->id, [$family['student']->id]);
    TenantContext::clear();

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    app(RedeemFamilyShareToken::class)
        ->execute($b['school']->id, $b['account']->person_id, $share['token']);

    $result = app(EnrollStudent::class)->execute(
        $b['school']->id,
        $b['year']->id,
        $family['student']->id,
        $b['account']->person_id,
    );
    TenantContext::clear();

    $stillActive = TenantContext::runWithRlsBypass(fn () => Enrollment::query()
        ->withoutGlobalScopes()
        ->find($family['enrollment']->id));

    expect($result)->toBeInstanceOf(EnrollmentTransfer::class)
        ->and($stillActive?->status)->toBe(EnrollmentStatus::Active);
});
