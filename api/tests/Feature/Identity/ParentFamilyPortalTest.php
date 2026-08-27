<?php

use App\Domain\Consent\Enums\ConsentScope;

it('lets a parent read attendance, issue a share token, and see the access log', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $schoolId = $school['school']->id;
    $childId = $family['student']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $this->provisionFeeSchedule($school)['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ])->assertOk();

    $teacher = $this->provisionTeacher($school, $classroom['id']);
    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => now()->toDateString(),
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'present',
            ]],
        ])->assertCreated();

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$childId}/attendance")
        ->assertOk()
        ->assertJsonPath('data.0.status', 'present');

    $share = $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/parent/share-tokens', [
            'child_person_ids' => [$childId],
        ])
        ->assertCreated()
        ->json();

    expect($share['token'])->not->toBeEmpty();

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->patchJson("/api/v1/parent/children/{$childId}", [
            'first_name' => 'Fanja',
        ])
        ->assertOk();

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/access-log')
        ->assertOk()
        ->assertJsonStructure(['data']);

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/consents')
        ->assertOk();

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/transfers')
        ->assertOk()
        ->assertJsonPath('data', []);

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/parent/consents', [
            'subject_person_id' => $childId,
            'grantee_school_id' => $schoolId,
            'scope' => ConsentScope::AcademicRecords->value,
            'purpose' => 'Partage des bulletins avec l’école d’accueil',
        ])
        ->assertCreated();
});

it('does not let a parent issue a share token for someone else’s child', function () {
    $school = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($school);
    $familyB = $this->provisionEnrolledFamily($school, parent: [
        'first_name' => 'Lala',
        'email' => 'lala.share@fanabe.test',
        'phone' => '0342223344',
    ], student: ['first_name' => 'Naina']);

    $this->actingAs($familyA['parentAccount'], 'sanctum')
        ->postJson('/api/v1/parent/share-tokens', [
            'child_person_ids' => [$familyB['student']->id],
        ])
        ->assertStatus(403);
});
