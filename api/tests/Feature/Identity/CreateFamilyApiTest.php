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
        ->assertJsonStructure(['invitation_code', 'family_id', 'enrollment_id']);

    $code = $response->json('invitation_code');

    $this->postJson('/api/v1/auth/invitations/claim', [
        'code' => $code,
        'email' => 'voahangy.login@fanabe.test',
        'password' => 'secret-pass',
    ])->assertOk()->assertJsonStructure(['token', 'person_id']);
});
