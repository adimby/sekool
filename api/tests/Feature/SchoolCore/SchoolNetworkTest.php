<?php

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\SchoolNetwork;

it('exposes sibling campus names without enrollments', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $c = $this->provisionSchool();

    TenantContext::runWithRlsBypass(function () use ($a, $b): void {
        $network = SchoolNetwork::query()->create(['name' => 'Réseau Analakanga']);
        $a['school']->forceFill(['network_id' => $network->id, 'name' => 'École Antsahabe', 'code' => 'antsahabe'])->save();
        $b['school']->forceFill(['network_id' => $network->id, 'name' => 'École Ambohipo', 'code' => 'ambohipo'])->save();
    });

    $payload = $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/network")
        ->assertOk()
        ->json('data');

    expect($payload['name'])->toBe('Réseau Analakanga')
        ->and(collect($payload['campuses'])->pluck('code')->sort()->values()->all())->toEqual(['ambohipo', 'antsahabe'])
        ->and($payload['campuses'][0])->not->toHaveKey('enrollments')
        ->and($payload['campuses'][0])->not->toHaveKey('students');

    $this->actingAs($c['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$c['school']->id}/network")
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('keeps classrooms isolated between campuses of the same network', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();

    TenantContext::runWithRlsBypass(function () use ($a, $b): void {
        $network = SchoolNetwork::query()->create(['name' => 'Réseau test']);
        $a['school']->forceFill(['network_id' => $network->id])->save();
        $b['school']->forceFill(['network_id' => $network->id])->save();
    });

    TenantContext::activate(TenantContext::forSchool($b['school']->id, $b['account']->person_id));
    $grade = GradeLevel::query()->create([
        'school_id' => $b['school']->id,
        'name' => '6ème',
        'stage' => GradeStage::Middle,
        'sequence' => 6,
    ]);
    $classroom = Classroom::query()->create([
        'school_id' => $b['school']->id,
        'school_year_id' => $b['year']->id,
        'grade_level_id' => $grade->id,
        'name' => '6ème A',
    ]);
    TenantContext::clear();

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$b['school']->id}/classrooms/{$classroom->id}")
        ->assertNotFound();

    $this->actingAs($a['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$a['school']->id}/classrooms/{$classroom->id}")
        ->assertNotFound();
});
