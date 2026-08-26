<?php

it('keeps authorization free of Collection and Reliability scores', function () {
    $files = [
        app_path('Domain/School/Support/SchoolGate.php'),
        app_path('Http/Middleware/EnsureSchoolRole.php'),
        app_path('Domain/Identity/Support/ParentAuthorization.php'),
    ];

    foreach ($files as $file) {
        $source = file_get_contents($file);
        expect($source)
            ->not->toContain('App\\Domain\\Collection')
            ->and($source)->not->toContain('App\\Domain\\Reliability')
            ->and($source)->not->toContain('RiskAssessment')
            ->and($source)->not->toContain('ReliabilityScore');
    }
});
