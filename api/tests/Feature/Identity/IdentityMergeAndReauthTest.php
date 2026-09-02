<?php

use App\Domain\Identity\Models\IdentityMerge;
use App\Domain\Identity\Models\Person;
use App\Domain\School\Enums\SchoolRole;

it('lets direction request a merge that only platform admin can approve, then keeps both public ids', function () {
    $school = $this->provisionSchool();
    $first = $this->provisionEnrolledFamily($school, [
        'first_name' => 'Voahangy',
        'last_name' => 'Rasoa',
        'phone' => '034 11 111 11',
    ], [
        'first_name' => 'Tiana',
        'last_name' => 'Rasoa',
    ]);
    $second = $this->provisionEnrolledFamily($school, [
        'first_name' => 'Voahangy',
        'last_name' => 'Rasoa',
        'phone' => '034 22 222 22',
        'email' => 'voahangy.bis.'.uniqid().'@fanabe.test',
    ], [
        'first_name' => 'Soa',
        'last_name' => 'Rasoa',
    ]);

    $teacher = $this->provisionTeacher($school);
    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/identity-merges", [
            'surviving_public_id' => $first['parent']->publicIdFormatted(),
            'duplicate_public_id' => $second['parent']->publicIdFormatted(),
            'reason' => 'Même responsable, deux dossiers.',
        ])
        ->assertForbidden();

    $created = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/identity-merges", [
            'surviving_public_id' => $first['parent']->publicIdFormatted(),
            'duplicate_public_id' => $second['parent']->publicIdFormatted(),
            'reason' => 'Même responsable, deux dossiers.',
        ])
        ->assertCreated()
        ->json('data');

    expect($created['status'])->toBe(IdentityMerge::REQUESTED)
        ->and($created['surviving']['id'])->toBe($first['parent']->id)
        ->and($created['duplicate']['id'])->toBe($second['parent']->id)
        ->and($created)->not->toHaveKey('enrollment_id');

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$school['school']->id}/identity-merges")
        ->assertOk()
        ->assertJsonPath('data.0.id', $created['id']);

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/platform/identity-merges/{$created['id']}/approve")
        ->assertNotFound();

    $platform = $this->provisionPlatformAdmin();

    $approved = $this->actingAs($platform, 'sanctum')
        ->postJson("/api/v1/platform/identity-merges/{$created['id']}/approve")
        ->assertOk()
        ->json('data');

    expect($approved['status'])->toBe(IdentityMerge::MERGED)
        ->and($approved['surviving']['first_name'])->toBe('Voahangy')
        ->and($approved['duplicate']['first_name'])->toBe('Voahangy')
        ->and($approved)->not->toHaveKey('bulletin')
        ->and(Person::findByPublicId($first['parent']->public_id)->id)->toBe($first['parent']->id)
        ->and(Person::findByPublicId($second['parent']->public_id)->id)->toBe($first['parent']->id)
        ->and($second['parent']->fresh()->merged_into_person_id)->toBe($first['parent']->id)
        ->and($second['parent']->fresh()->public_id)->toBe($second['parent']->public_id);

    $undone = $this->actingAs($platform, 'sanctum')
        ->postJson("/api/v1/platform/identity-merges/{$created['id']}/undo")
        ->assertOk()
        ->json('data');

    expect($undone['status'])->toBe(IdentityMerge::UNDONE)
        ->and($second['parent']->fresh()->merged_into_person_id)->toBeNull()
        ->and(Person::findByPublicId($second['parent']->public_id)->id)->toBe($second['parent']->id);
});

it('warns about similar persons without blocking family creation', function () {
    $school = $this->provisionSchool();
    $this->provisionEnrolledFamily($school, [
        'first_name' => 'Mialy',
        'last_name' => 'Rakoto',
        'phone' => '034 12 345 67',
    ]);

    $created = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$school['school']->id}/families", [
            'school_year_id' => $school['year']->id,
            'parent' => [
                'first_name' => 'Mialy',
                'last_name' => 'Rakoto',
                'phone' => '034 99 000 00',
                'email' => 'mialy.autre@fanabe.test',
            ],
            'student' => [
                'first_name' => 'Hery',
                'last_name' => 'Rakoto',
                'birth_date' => '2014-01-12',
            ],
        ])
        ->assertCreated()
        ->json();

    expect($created['warnings'])->not->toBeEmpty()
        ->and(collect($created['warnings'])->pluck('last_name')->unique()->all())->toContain('Rakoto')
        ->and($created['family_id'])->not->toBeEmpty();

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$school['school']->id}/people/duplicates?first_name=Mialy&last_name=Rakoto")
        ->assertOk()
        ->assertJsonFragment(['last_name' => 'Rakoto']);
});

it('requires a password reauth before a parent can export their archive', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/export')
        ->assertForbidden()
        ->assertJsonPath('message', 'Confirmez votre identité pour continuer.');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/auth/reauth', ['password' => 'wrong-password'])
        ->assertUnprocessable();

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->postJson('/api/v1/auth/reauth', ['password' => 'password'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/export')
        ->assertOk()
        ->assertJsonPath('person.id', $family['parent']->id);
});

it('requires a TOTP reauth before direction can revoke a certificate', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $issued = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/certificates")
        ->assertCreated()
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/certificates/{$issued['id']}/revoke", [
            'reason' => 'Erreur de classe',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Confirmez votre identité pour continuer.');

    $this->flushHeaders();
    $session = $this->loginJson($school['account']->email);
    $token = $session->json('token');

    $hint = $this->withToken($token)
        ->getJson('/api/v1/auth/reauth')
        ->assertOk()
        ->assertJsonPath('method', 'totp')
        ->json();

    expect($hint['demo_code'])->toHaveLength(6);

    $this->withToken($token)
        ->postJson('/api/v1/auth/reauth', ['code' => '000000'])
        ->assertUnprocessable();

    $this->withToken($token)
        ->postJson('/api/v1/auth/reauth', ['code' => $hint['demo_code']])
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/schools/{$schoolId}/certificates/{$issued['id']}/revoke", [
            'reason' => 'Erreur de classe',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked');
});

it('returns 404 on platform merge routes for a school admin', function () {
    $school = $this->provisionSchool(SchoolRole::Admin);

    $this->actingAs($school['account'], 'sanctum')
        ->getJson('/api/v1/platform/identity-merges')
        ->assertNotFound();
});
