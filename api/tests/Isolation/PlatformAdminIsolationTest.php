<?php

it('never lets a platform admin read a school’s écolage, invoices, enrollments or people', function () {
    $fixture = $this->provisionSchool();
    $this->provisionFeeSchedule($fixture);
    $this->provisionEnrolledFamily($fixture);
    $platform = $this->provisionPlatformAdmin();
    $schoolId = $fixture['school']->id;

    foreach ([
        "/api/v1/schools/{$schoolId}/payments/export",
        "/api/v1/schools/{$schoolId}/fee-schedules",
        "/api/v1/schools/{$schoolId}/collection/queue",
        "/api/v1/schools/{$schoolId}/enrollments",
        "/api/v1/schools/{$schoolId}/people",
        "/api/v1/schools/{$schoolId}/classrooms",
    ] as $path) {
        $this->actingAs($platform, 'sanctum')
            ->getJson($path)
            ->assertNotFound()
            ->assertJsonPath('message', 'Not found.');
    }

    $directory = $this->actingAs($platform, 'sanctum')
        ->getJson("/api/v1/platform/schools/{$schoolId}")
        ->assertOk()
        ->json('data');

    expect($directory)
        ->toHaveKey('name')
        ->and($directory)->not->toHaveKey('remaining_amount')
        ->and($directory)->not->toHaveKey('invoices')
        ->and($directory)->not->toHaveKey('payments')
        ->and($directory)->not->toHaveKey('headcount')
        ->and($directory)->not->toHaveKey('enrollments');
});
