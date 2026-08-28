<?php

it('authenticates a school user with email and password', function () {
    $fixture = $this->provisionSchool();

    $this->postJson('/api/v1/auth/login', [
        'email' => $fixture['account']->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonStructure(['token', 'person_id', 'person', 'schools', 'is_parent', 'is_student'])
        ->assertJsonPath('schools.0.id', $fixture['school']->id)
        ->assertJsonPath('is_student', false);
});

it('returns the session payload on /me', function () {
    $fixture = $this->provisionSchool();

    $this->actingAs($fixture['account'], 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('person_id', $fixture['account']->person_id)
        ->assertJsonPath('schools.0.id', $fixture['school']->id);
});

it('rejects a wrong password without revealing whether the email exists', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@fanabe.test',
        'password' => 'wrong',
    ])->assertUnprocessable();
});
