<?php

it('records a school purchase in the finance register', function () {
    $school = $this->provisionSchool();
    $schoolId = $school['school']->id;

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/expenses", [
            'school_year_id' => $school['year']->id,
            'kind' => 'purchase',
            'category' => 'supplies',
            'label' => 'Cahiers',
            'amount' => 85_000,
            'spent_on' => '2026-09-05',
            'vendor' => 'Papeterie Analakely',
        ])
        ->assertCreated()
        ->assertJsonPath('data.amount', 85_000)
        ->assertJsonPath('data.kind', 'purchase');

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/expenses?school_year_id={$school['year']->id}")
        ->assertOk()
        ->assertJsonPath('total_amount', 85_000)
        ->assertJsonPath('data.0.label', 'Cahiers');
});

it('hides expenses from a teacher', function () {
    $school = $this->provisionSchool();
    $teacher = $this->provisionTeacher($school);

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$school['school']->id}/expenses")
        ->assertForbidden();
});
