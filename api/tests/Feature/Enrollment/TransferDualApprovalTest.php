<?php

use App\Domain\Enrollment\Actions\ApproveEnrollmentTransfer;
use App\Domain\Enrollment\Actions\EnrollStudent;
use App\Domain\Enrollment\Actions\RefuseEnrollmentTransfer;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Enums\TransferStatus;
use App\Domain\Enrollment\Models\EnrollmentTransfer;
use App\Domain\Identity\Actions\GenerateFamilyShareToken;
use App\Domain\Identity\Actions\RedeemFamilyShareToken;
use App\Domain\Platform\Tenancy\TenantContext;

function startTransfer(): array
{
    $origin = test()->provisionSchool();
    $destination = test()->provisionSchool();
    $family = test()->provisionEnrolledFamily($origin);

    TenantContext::activate(TenantContext::identifiedPerson($family['parent']->id));
    $share = app(GenerateFamilyShareToken::class)->execute($family['parent']->id, [$family['student']->id]);
    TenantContext::clear();

    TenantContext::activate(TenantContext::forSchool($destination['school']->id, $destination['account']->person_id));
    app(RedeemFamilyShareToken::class)->execute($destination['school']->id, $destination['account']->person_id, $share['token']);
    $transfer = app(EnrollStudent::class)->execute(
        $destination['school']->id,
        $destination['year']->id,
        $family['student']->id,
        $destination['account']->person_id,
    );
    TenantContext::clear();

    return compact('origin', 'destination', 'family', 'transfer');
}

it('does not complete a transfer without both parent and origin-school approval', function () {
    ['origin' => $origin, 'family' => $family, 'transfer' => $transfer] = startTransfer();

    expect($transfer)->toBeInstanceOf(EnrollmentTransfer::class)
        ->and($transfer->status)->toBe(TransferStatus::PendingParent);

    $afterParent = app(ApproveEnrollmentTransfer::class)->byParent($transfer->id, $family['parent']->id);

    expect($afterParent->status)->toBe(TransferStatus::PendingOriginSchool);

    $stillActive = TenantContext::runWithRlsBypass(fn () => $family['enrollment']->newQuery()
        ->withoutGlobalScopes()
        ->find($family['enrollment']->id));
    expect($stillActive->status)->toBe(EnrollmentStatus::Active);

    TenantContext::activate(TenantContext::forSchool($origin['school']->id, $origin['account']->person_id));
    $completed = app(ApproveEnrollmentTransfer::class)->byOriginSchool(
        $transfer->id,
        $origin['school']->id,
        $origin['account']->person_id,
    );
    TenantContext::clear();

    expect($completed->status)->toBe(TransferStatus::Completed);

    $originEnrollment = TenantContext::runWithRlsBypass(fn () => $family['enrollment']->newQuery()
        ->withoutGlobalScopes()
        ->find($family['enrollment']->id));

    expect($originEnrollment->status)->toBe(EnrollmentStatus::TransferredOut);
});

it('leaves the student at the origin school when the origin refuses', function () {
    ['origin' => $origin, 'family' => $family, 'transfer' => $transfer] = startTransfer();

    app(ApproveEnrollmentTransfer::class)->byParent($transfer->id, $family['parent']->id);

    TenantContext::activate(TenantContext::forSchool($origin['school']->id, $origin['account']->person_id));
    $refused = app(RefuseEnrollmentTransfer::class)->byOriginSchool($transfer->id, $origin['school']->id, 'Reste inscrit ici');
    TenantContext::clear();

    expect($refused->status)->toBe(TransferStatus::Rejected);

    $stillThere = TenantContext::runWithRlsBypass(fn () => $family['enrollment']->newQuery()
        ->withoutGlobalScopes()
        ->find($family['enrollment']->id));
    expect($stillThere->status)->toBe(EnrollmentStatus::Active);
});
