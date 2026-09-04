<?php

use App\Domain\School\Support\SchoolCapabilities;

it('maps school roles to menus and writes, fail-closed for staff', function () {
    expect(SchoolCapabilities::for(['school_admin'], false, false))->toMatchArray([
        'accueil' => true,
        'famille' => true,
        'classe' => true,
        'finance' => true,
        'caisse' => true,
        'kits' => true,
        'indices' => true,
        'appel' => false,
        'vie' => false,
        'notes' => false,
        'titulaire' => false,
        'enseigne' => false,
    ]);

    expect(SchoolCapabilities::for(['principal'], false, false)['caisse'])->toBeFalse()
        ->and(SchoolCapabilities::for(['principal'], false, false)['finance'])->toBeTrue()
        ->and(SchoolCapabilities::for(['principal'], false, false)['accueil'])->toBeTrue();

    expect(SchoolCapabilities::for(['accountant'], false, false))->toMatchArray([
        'accueil' => false,
        'famille' => false,
        'classe' => false,
        'finance' => true,
        'caisse' => true,
        'kits' => true,
        'indices' => false,
        'appel' => false,
        'vie' => false,
        'notes' => false,
        'titulaire' => false,
        'enseigne' => false,
    ]);

    expect(SchoolCapabilities::for(['teacher'], true, true)['vie'])->toBeTrue()
        ->and(SchoolCapabilities::for(['teacher'], true, true)['notes'])->toBeFalse()
        ->and(SchoolCapabilities::for(['teacher'], true, true)['kits'])->toBeTrue();

    expect(SchoolCapabilities::for(['teacher'], false, true)['vie'])->toBeFalse()
        ->and(SchoolCapabilities::for(['teacher'], false, true)['notes'])->toBeTrue()
        ->and(SchoolCapabilities::for(['teacher'], false, true)['appel'])->toBeTrue()
        ->and(SchoolCapabilities::for(['teacher'], false, true)['kits'])->toBeFalse();

    expect(SchoolCapabilities::for(['staff'], false, false)['accueil'])->toBeFalse()
        ->and(SchoolCapabilities::for(['staff'], false, false)['caisse'])->toBeFalse()
        ->and(SchoolCapabilities::for([], false, true)['notes'])->toBeFalse();
});
