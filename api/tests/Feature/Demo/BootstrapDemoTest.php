<?php

use App\Domain\School\Models\School;

it('serves a placeholder when the built UI is absent', function () {
    $this->get('/')->assertStatus(503);
});

it('bootstraps demo data only once', function () {
    $this->artisan('demo:bootstrap')->assertSuccessful();
    $count = School::query()->count();
    expect($count)->toBeGreaterThan(0);

    $this->artisan('demo:bootstrap')->assertSuccessful();
    expect(School::query()->count())->toBe($count);
});
