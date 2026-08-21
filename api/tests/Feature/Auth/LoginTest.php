<?php

it('authenticates a school user with email and password', function () {
    $fixture = $this->provisionSchool();

    $this->postJson('/api/v1/auth/login', [
        'email' => $fixture['account']->email,
        'password' => 'password',
    ])->assertOk()->assertJsonStructure(['token', 'person_id']);
});

it('rejects a wrong password without revealing whether the email exists', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@fanabe.test',
        'password' => 'wrong',
    ])->assertUnprocessable();
});
