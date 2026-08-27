<?php

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Finance\Actions\UnlockFeeSchedule;
use App\Domain\Finance\Enums\FeeCategory;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\SchoolRoleAssignment;
use App\Domain\School\Models\SchoolYear;

function feeItemPayload(int $amount = 50_000, string $suffix = 'T1'): array
{
    return [
        'code' => 'SCOL_'.$suffix,
        'label' => 'Écolage '.$suffix,
        'amount' => $amount,
        'due_on' => '2026-09-15',
        'category' => FeeCategory::Tuition->value,
    ];
}

it('copies last year’s barème and applies a percentage or Ariary difference', function () {
    $school = $this->provisionSchool();
    $schoolId = $school['school']->id;

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    $previous = SchoolYear::factory()->create([
        'school_id' => $schoolId,
        'label' => '2025-2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-07-15',
        'is_current' => false,
    ]);
    TenantContext::clear();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules", [
            'school_year_id' => $previous->id,
            'name' => 'Écolage 2025-2026',
            'items' => [
                feeItemPayload(50_000, 'T1'),
                [
                    'code' => 'INSCR',
                    'label' => 'Droit d’inscription',
                    'amount' => 20_000,
                    'due_on' => '2025-09-01',
                    'category' => FeeCategory::Registration->value,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.locked', false);

    $copied = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules/copy-year", [
            'source_year_id' => $previous->id,
            'target_year_id' => $school['year']->id,
            'adjustment_type' => 'percent',
            'adjustment_percent' => 10,
        ])
        ->assertCreated()
        ->json('data');

    expect($copied)->toHaveCount(1)
        ->and($copied[0]['status'])->toBe('draft')
        ->and($copied[0]['items'][0]['amount'])->toBe(22_000)
        ->and($copied[0]['items'][1]['amount'])->toBe(55_000)
        ->and($copied[0]['items'][0]['due_on'])->toBe('2026-09-01')
        ->and($copied[0]['items'][1]['due_on'])->toBe('2027-09-15');

    $adjusted = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules/{$copied[0]['id']}/adjust", [
            'adjustment_type' => 'amount',
            'adjustment_amount' => -5_000,
        ])
        ->assertOk()
        ->json('data');

    expect($adjusted['items'][0]['amount'])->toBe(17_000)
        ->and($adjusted['items'][1]['amount'])->toBe(50_000);
});

it('locks a barème after two administrative validations and refuses later edits', function () {
    $school = $this->provisionSchool();
    $schoolId = $school['school']->id;

    $created = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules", [
            'school_year_id' => $school['year']->id,
            'name' => 'Barème 6ème',
            'items' => [feeItemPayload()],
        ])
        ->assertCreated()
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}/confirm")
        ->assertStatus(422);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_validation');

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}", [
            'items' => [feeItemPayload(60_000)],
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.items.0.amount', 60_000);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}/submit")
        ->assertOk();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.locked', true);

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}", [
            'items' => [feeItemPayload(80_000)],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Ce barème est verrouillé. Toute modification exige une demande de support FANABE.');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}/request-unlock", [
            'reason' => 'Erreur de saisie sur l’écolage T1',
        ])
        ->assertOk()
        ->assertJsonPath('data.locked', true)
        ->assertJsonPath('data.unlock_request_reason', 'Erreur de saisie sur l’écolage T1');

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}", [
            'name' => 'Ne doit pas passer',
        ])
        ->assertStatus(422);

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    app(UnlockFeeSchedule::class)->execute(
        $schoolId,
        $created['id'],
        $school['account']->person_id,
        'Correction demandée par la direction',
    );
    TenantContext::clear();

    $this->actingAs($school['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}", [
            'name' => 'Barème corrigé',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.locked', false)
        ->assertJsonPath('data.name', 'Barème corrigé');
});

it('prefers a grade-level barème over the school-wide fallback when invoicing', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    $grade = GradeLevel::query()->where('name', '6ème')->firstOrFail();
    TenantContext::clear();

    $specific = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $grade->id,
            'name' => '6ème 2026-2027',
            'items' => [[
                'code' => 'INSCR',
                'label' => 'Droit d’inscription',
                'amount' => 10_000,
                'due_on' => '2026-09-01',
                'category' => FeeCategory::Registration->value,
            ]],
        ])
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules/{$specific['id']}/submit");
    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules/{$specific['id']}/confirm")
        ->assertJsonPath('data.locked', true);

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $grade->id,
            'name' => '6ème A',
        ])
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->assertCreated()
        ->assertJsonPath('data.net_amount', 10_000);

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/roster")
        ->assertOk()
        ->assertJsonPath('data.fee_schedule.locked', true)
        ->assertJsonPath('data.fee_schedule.total_amount', 10_000)
        ->assertJsonPath('data.classroom.grade_level.name', '6ème');
});

it('lets finance read barèmes but not change them, and hides them from teachers', function () {
    $school = $this->provisionSchool();
    $accountant = $this->provisionSchool(SchoolRole::Accountant);
    $schoolId = $school['school']->id;

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    SchoolRoleAssignment::query()->create([
        'school_id' => $schoolId,
        'person_id' => $accountant['account']->person_id,
        'role' => SchoolRole::Accountant,
        'granted_at' => now(),
    ]);
    TenantContext::clear();

    $created = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/fee-schedules", [
            'school_year_id' => $school['year']->id,
            'name' => 'Écolage',
            'items' => [feeItemPayload()],
        ])
        ->json('data');

    $this->actingAs($accountant['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/fee-schedules")
        ->assertOk()
        ->assertJsonPath('data.0.id', $created['id']);

    $this->actingAs($accountant['account'], 'sanctum')
        ->patchJson("/api/v1/schools/{$schoolId}/fee-schedules/{$created['id']}", [
            'name' => 'Interdit',
        ])
        ->assertForbidden();

    $teacher = $this->provisionTeacher($school);
    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/fee-schedules")
        ->assertForbidden();
});
