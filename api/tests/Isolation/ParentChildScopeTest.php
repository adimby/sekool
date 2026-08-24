<?php

it('lets a parent see only the children they are authorized to see', function () {
    $school = $this->provisionSchool();
    $familyA = $this->provisionEnrolledFamily($school, [
        'first_name' => 'Soa',
        'last_name' => 'Andria',
        'email' => 'soa.andria@fanabe.test',
        'phone' => '0341111111',
    ], [
        'first_name' => 'Hery',
        'last_name' => 'Andria',
    ]);
    $familyB = $this->provisionEnrolledFamily($school, [
        'first_name' => 'Lala',
        'last_name' => 'Rabe',
        'email' => 'lala.rabe@fanabe.test',
        'phone' => '0342222222',
    ], [
        'first_name' => 'Naina',
        'last_name' => 'Rabe',
    ]);

    $this->actingAs($familyA['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/children')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $familyA['student']->id);

    $this->actingAs($familyA['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/children/'.$familyB['student']->id)
        ->assertNotFound();
});
