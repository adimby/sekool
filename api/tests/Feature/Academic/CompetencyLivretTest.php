<?php

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;

it('records a competency on the preschool livret and shows it to the parent, without photos', function () {
    $school = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    $gs = GradeLevel::query()->create([
        'school_id' => $schoolId,
        'name' => 'GS',
        'stage' => GradeStage::Preschool,
        'sequence' => 0,
    ]);
    $group = Classroom::query()->create([
        'school_id' => $schoolId,
        'school_year_id' => $school['year']->id,
        'grade_level_id' => $gs->id,
        'name' => 'GS A',
        'capacity' => 20,
    ]);
    TenantContext::clear();

    $family = $this->provisionEnrolledFamily($school, student: ['first_name' => 'Naina', 'last_name' => 'Rasoa']);
    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $group->id,
        ]);

    $teacher = $this->provisionTeacher($school, $group->id);
    $otherClass = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème B',
        ])->json('data');
    $outsider = $this->provisionTeacher($school, $otherClass['id']);

    $livret = $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/classrooms/{$group->id}/competencies")
        ->assertOk()
        ->assertJsonPath('competencies_enabled', true)
        ->json();

    expect($livret)->not->toHaveKey('photo')
        ->and($livret['domains'])->not->toBeEmpty();

    $itemId = $livret['domains'][0]['items'][0]['id'];
    expect($livret['domains'][0]['items'][0])->not->toHaveKey('photo');

    $recorded = $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$group->id}/competencies", [
            'enrollment_id' => $family['enrollment']->id,
            'competency_item_id' => $itemId,
            'level' => 'acquired',
            'comment' => 'S’exprime clairement en cercle.',
            'assessed_on' => '2026-09-04',
            'photo' => 'http://example.test/interdit.jpg',
        ])
        ->assertCreated()
        ->assertJsonPath('data.level', 'acquired')
        ->assertJsonPath('data.level_label', 'Acquis')
        ->assertJsonMissingPath('data.photo')
        ->json('data');

    expect($recorded)->not->toHaveKey('photo')
        ->and($recorded)->not->toHaveKey('photo_url');

    $this->actingAs($outsider['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$group->id}/competencies", [
            'enrollment_id' => $family['enrollment']->id,
            'competency_item_id' => $itemId,
            'level' => 'in_progress',
            'assessed_on' => '2026-09-05',
        ])
        ->assertNotFound();

    $this->actingAs($outsider['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$otherClass['id']}/competencies", [
            'enrollment_id' => $family['enrollment']->id,
            'competency_item_id' => $itemId,
            'level' => 'acquired',
            'assessed_on' => '2026-09-04',
        ])
        ->assertStatus(422);

    $parentLivret = $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/competencies")
        ->assertOk()
        ->assertJsonPath('competencies_enabled', true)
        ->json();

    expect($parentLivret['assessments'])->toHaveCount(1)
        ->and($parentLivret['assessments'][0]['level'])->toBe('acquired')
        ->and($parentLivret['assessments'][0]['comment'])->toBe('S’exprime clairement en cercle.')
        ->and($parentLivret)->not->toHaveKey('photo');

    $financeAdult = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/families/{$family['family']->id}/adults", [
            'first_name' => 'Soa',
            'last_name' => 'Andria',
            'email' => 'soa.'.uniqid().'@fanabe.test',
            'relationship' => RelationshipType::FinancialContactFor->value,
        ])
        ->assertSuccessful()
        ->json('adult');

    $financeAccount = UserAccount::factory()->create([
        'person_id' => $financeAdult['id'],
        'email' => 'finance.'.uniqid().'@fanabe.test',
        'password' => 'password',
    ]);

    $this->actingAs($financeAccount, 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/competencies")
        ->assertNotFound();
});
