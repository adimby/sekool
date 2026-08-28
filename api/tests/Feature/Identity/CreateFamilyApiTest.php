<?php

it('lets a school create a family, a student and a parent invitation', function () {
    $school = $this->provisionSchool();

    $response = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/families", [
            'school_year_id' => $school['year']->id,
            'parent' => [
                'first_name' => 'Voahangy',
                'last_name' => 'Rasoa',
                'phone' => '0349876543',
                'email' => 'voahangy.rasoa@fanabe.test',
            ],
            'student' => [
                'first_name' => 'Tiana',
                'last_name' => 'Rasoa',
                'birth_date' => '2014-06-20',
                'sex' => 'female',
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('parent.first_name', 'Voahangy')
        ->assertJsonPath('parent.phone_e164', '+261349876543')
        ->assertJsonPath('student.first_name', 'Tiana')
        ->assertJsonPath('family_label', 'Rasoa')
        ->assertJsonStructure(['invitation_code', 'family_id', 'enrollment_id']);

    $code = $response->json('invitation_code');

    $this->postJson('/api/v1/auth/invitations/claim', [
        'code' => $code,
        'email' => 'voahangy.login@fanabe.test',
        'password' => 'secret-pass',
    ])->assertOk()->assertJsonStructure(['token', 'person_id']);
});

it('names the family after the student unless a label is given', function () {
    $school = $this->provisionSchool();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/families", [
            'school_year_id' => $school['year']->id,
            'label' => 'Rasoa-Rakoto',
            'parent' => [
                'first_name' => 'Voahangy',
                'last_name' => 'Andria',
                'phone' => '0349876544',
            ],
            'student' => [
                'first_name' => 'Tiana',
                'last_name' => 'Rasoa',
                'birth_date' => '2014-06-20',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('family_label', 'Rasoa-Rakoto');
});

it('assigns the chosen classroom at enrollment', function () {
    $school = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])
        ->assertCreated()
        ->json('data');

    $created = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/families", [
            'school_year_id' => $school['year']->id,
            'classroom_id' => $classroom['id'],
            'parent' => [
                'first_name' => 'Voahangy',
                'last_name' => 'Rasoa',
                'phone' => '0349876545',
            ],
            'student' => [
                'first_name' => 'Tiana',
                'last_name' => 'Rasoa',
                'birth_date' => '2014-06-20',
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('classroom_id', $classroom['id']);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/families/{$created->json('family_id')}/children", [
            'school_year_id' => $school['year']->id,
            'classroom_id' => $classroom['id'],
            'first_name' => 'Soa',
            'last_name' => 'Rasoa',
            'birth_date' => '2016-03-12',
        ])
        ->assertCreated()
        ->assertJsonPath('classroom_id', $classroom['id']);
});
