<?php

use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Communication\Models\Message;
use App\Domain\Communication\Support\MessageCatalog;
use App\Domain\Platform\Tenancy\TenantContext;

it('lists print letters for direction, marks them handed, and exports the family archive', function () {
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

    $teacher = $this->provisionTeacher($school, $classroom['id']);

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => now()->toDateString(),
            'session' => AttendanceSession::FullDay->value,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Absent->value,
                'reason' => 'Maladie',
                'justification' => 'Certificat médical vu à l’accueil',
                'client_reference' => 'bbbbbbbb-2222-4222-8222-222222222222',
            ]],
        ])
        ->assertCreated();

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/messages/outbox")
        ->assertForbidden();

    $outbox = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/messages/outbox")
        ->assertOk()
        ->json('data');

    expect($outbox)->not->toBeEmpty()
        ->and(collect($outbox)->pluck('channel')->unique()->all())->toBe(['print'])
        ->and(collect($outbox)->pluck('delivery_status')->unique()->all())->toBe(['ready_to_print'])
        ->and($outbox[0]['student']['id'])->toBe($family['student']->id);

    $letterId = $outbox[0]['id'];

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/messages/{$letterId}/printed")
        ->assertForbidden();

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/messages/{$letterId}/printed")
        ->assertOk()
        ->assertJsonPath('data.delivery_status', 'printed');

    $remaining = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/messages/outbox")
        ->assertOk()
        ->json('data');

    expect(collect($remaining)->pluck('id'))->not->toContain($letterId);

    $archive = $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/export')
        ->assertOk()
        ->json();

    expect($archive['notice'])->toContain('Pas un LSU')
        ->and($archive['person']['id'])->toBe($family['parent']->id)
        ->and(collect($archive['children'])->pluck('person.id'))->toContain($family['student']->id);

    $encoded = mb_strtolower(json_encode($archive));
    foreach (MessageCatalog::forbiddenFamilyTerms() as $term) {
        expect($encoded)->not->toContain(mb_strtolower($term));
    }

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    expect(Message::query()->where('id', $letterId)->where('channel', 'print')->exists())->toBeTrue();
    TenantContext::clear();
});
