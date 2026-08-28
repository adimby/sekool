<?php

it('applies grade packs idempotently', function () {
    $school = $this->provisionSchool();
    $schoolId = $school['school']->id;

    $first = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/grade-levels/packs", [
            'packs' => ['preschool', 'middle'],
        ])
        ->assertOk()
        ->json('data');

    expect($first['created'])->toEqual(['PS', 'MS', 'GS', '6ème', '5ème', '4ème', '3ème'])
        ->and($first['skipped'])->toBe([])
        ->and(collect($first['grades'])->pluck('name')->all())->toEqual([
            'PS', 'MS', 'GS', '6ème', '5ème', '4ème', '3ème',
        ])
        ->and(collect($first['grades'])->firstWhere('name', 'GS')['stage'])->toBe('preschool')
        ->and(collect($first['grades'])->firstWhere('name', '6ème')['stage'])->toBe('middle');

    $second = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/grade-levels/packs", [
            'packs' => ['preschool', 'high'],
        ])
        ->assertOk()
        ->json('data');

    expect($second['created'])->toEqual(['Seconde', 'Première', 'Terminale'])
        ->and($second['skipped'])->toEqual(['PS', 'MS', 'GS']);
});

it('rejects an unknown grade pack', function () {
    $school = $this->provisionSchool();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/grade-levels/packs", [
            'packs' => ['college'],
        ])
        ->assertStatus(422);
});
