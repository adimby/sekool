<?php

use App\Domain\Collection\Actions\RecomputeCollection;
use App\Domain\Collection\Support\FamilyRecipients;
use App\Domain\Communication\Support\MessageCatalog;
use App\Domain\Finance\Models\Installment;
use App\Domain\Platform\Audit\AuditEvent;
use App\Domain\Platform\Tenancy\TenantContext;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'Indian/Antananarivo'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('shows three justified cockpit actions, records a relance, and hides risk from the family', function () {
    $school = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $fanja = $this->provisionEnrolledFamily($school, student: ['first_name' => 'Fanja', 'last_name' => 'Rakoto']);
    $hery = $this->provisionEnrolledFamily($school, student: ['first_name' => 'Hery', 'last_name' => 'Rasoanaivo']);
    $tojo = $this->provisionEnrolledFamily($school, student: ['first_name' => 'Tojo', 'last_name' => 'Andrianina']);

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    foreach ([$fanja, $hery, $tojo] as $family) {
        $this->actingAs($school['account'], 'sanctum')
            ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
                'classroom_id' => $classroom['id'],
            ])->assertOk();
    }

    $teacher = $this->provisionTeacher($school, $classroom['id']);

    foreach ([$fanja, $hery] as $family) {
        $this->actingAs($school['account'], 'sanctum')
            ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
            ->assertCreated();
    }

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    backdateFirstInstallment($fanja['enrollment']->id, '2026-06-17');
    backdateFirstInstallment($hery['enrollment']->id, '2026-08-10');
    TenantContext::clear();

    foreach ([0, 1, 2] as $daysAgo) {
        $this->actingAs($teacher['account'], 'sanctum')
            ->postJson("/api/v1/schools/{$schoolId}/attendance", [
                'date' => Carbon::now('Indian/Antananarivo')->subDays($daysAgo)->toDateString(),
                'session' => 'full_day',
                'records' => [[
                    'enrollment_id' => $tojo['enrollment']->id,
                    'status' => 'absent',
                ]],
            ])->assertCreated();
    }

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    app(RecomputeCollection::class)->execute($schoolId, live: true);
    TenantContext::clear();

    $cockpit = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/cockpit")
        ->assertOk()
        ->json();

    expect($cockpit['actions'])->toHaveCount(3);
    $keys = collect($cockpit['actions'])->pluck('template_key')->all();
    expect($keys)->toContain('payment_overdue')
        ->and($keys)->toContain('repeated_absence')
        ->and($cockpit['actions'][0]['reason_summary'])->not->toBe('')
        ->and($cockpit['risk_counts']['critical'])->toBeGreaterThan(0);

    $taskId = $cockpit['actions'][0]['id'];

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/collection/tasks/{$taskId}/relance")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    TenantContext::runWithRlsBypass(function () use ($taskId): void {
        expect(AuditEvent::query()->where('action', 'collection.task.relanced')->where('resource_id', $taskId)->exists())->toBeTrue();
    });

    $inbox = $this->actingAs($fanja['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/messages')
        ->assertOk()
        ->json('data');

    expect($inbox)->not->toBeEmpty();
    $haystack = mb_strtolower(collect($inbox)->map(fn (array $row): string => $row['subject'].' '.$row['body'])->implode(' '));
    foreach (MessageCatalog::forbiddenFamilyTerms() as $term) {
        expect($haystack)->not->toContain(mb_strtolower($term));
    }

    $familyId = FamilyRecipients::familyIdForStudent((string) $fanja['student']->id);
    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/families/{$familyId}/reliability")
        ->assertOk()
        ->assertJsonPath('data.calculator_version', 'family-reliability.v1');

    $this->actingAs($fanja['parentAccount'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/families/{$familyId}/reliability")
        ->assertNotFound();

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/cockpit")
        ->assertForbidden();
});

it('does not create live messages during a dry-run workflow', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);
    $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->assertCreated();

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    backdateFirstInstallment($family['enrollment']->id, '2026-06-01');
    app(RecomputeCollection::class)->execute($schoolId, live: false);
    TenantContext::clear();

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/cockpit")
        ->assertOk()
        ->assertJsonCount(0, 'actions');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/messages')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

function backdateFirstInstallment(string $enrollmentId, string $dueOn): void
{
    $installment = Installment::query()
        ->whereHas('invoice', fn ($query) => $query->where('enrollment_id', $enrollmentId))
        ->orderBy('sequence')
        ->firstOrFail();
    $installment->forceFill(['due_on' => $dueOn])->save();
    $installment->refreshDerivedStatus();
    $installment->save();
}
