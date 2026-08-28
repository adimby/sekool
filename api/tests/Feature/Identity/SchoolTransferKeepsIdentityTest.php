<?php

use App\Domain\Enrollment\Actions\ApproveEnrollmentTransfer;
use App\Domain\Enrollment\Actions\EnrollStudent;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Enums\TransferStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Identity\Actions\GenerateFamilyShareToken;
use App\Domain\Identity\Actions\RedeemFamilyShareToken;
use App\Domain\Platform\Tenancy\TenantContext;

it('keeps the same Person identity when a student changes school', function () {
    $origin = $this->provisionSchool();
    $destination = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($origin);
    $personId = $family['student']->id;
    $publicId = $family['student']->public_id;

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    $share = app(GenerateFamilyShareToken::class)->execute($family['parent']->id, [$personId]);
    TenantContext::clear();

    TenantContext::activate(TenantContext::forSchool($destination['school']->id, $destination['account']->person_id));
    app(RedeemFamilyShareToken::class)->execute($destination['school']->id, $destination['account']->person_id, $share['token']);
    $transfer = app(EnrollStudent::class)->execute(
        $destination['school']->id,
        $destination['year']->id,
        $personId,
        $destination['account']->person_id,
    );
    TenantContext::clear();

    expect($transfer)->toBeInstanceOf(EnrollmentTransfer::class);

    app(ApproveEnrollmentTransfer::class)->byParent($transfer->id, $family['parent']->id);

    TenantContext::activate(TenantContext::forSchool($origin['school']->id, $origin['account']->person_id));
    $completed = app(ApproveEnrollmentTransfer::class)->byOriginSchool(
        $transfer->id,
        $origin['school']->id,
        $origin['account']->person_id,
    );
    TenantContext::clear();

    expect($completed->status)->toBe(TransferStatus::Completed)
        ->and($family['student']->fresh()->id)->toBe($personId)
        ->and($family['student']->fresh()->public_id)->toBe($publicId);

    $originEnrollment = TenantContext::runWithRlsBypass(fn () => Enrollment::query()
        ->withoutGlobalScopes()
        ->find($family['enrollment']->id));
    $destEnrollment = TenantContext::runWithRlsBypass(fn () => Enrollment::query()
        ->withoutGlobalScopes()
        ->where('person_id', $personId)
        ->where('school_id', $destination['school']->id)
        ->where('status', EnrollmentStatus::Active)
        ->first());

    expect($originEnrollment->status)->toBe(EnrollmentStatus::TransferredOut)
        ->and($destEnrollment)->not->toBeNull()
        ->and($destEnrollment->person_id)->toBe($personId);
});
