<?php

use App\Domain\Collection\Actions\RecomputeCollection;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\Reliability\Actions\ComputeFamilyReliability;
use App\Domain\Reliability\Actions\ComputeRelationshipHealth;
use App\Domain\Reliability\Models\ReliabilityScore;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\Reliability\Support\FamilyReliabilityCalculator;
use App\Domain\Reliability\Support\ReliabilityIndexes;
use Carbon\Carbon;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'Indian/Antananarivo'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('exposes school reliability, relationship health, versioning and comparison to direction only', function () {
    $school = $this->provisionSchool();
    $core = $this->provisionFeeSchedule($school);
    $schoolId = $school['school']->id;
    $family = $this->provisionEnrolledFamily($school, student: ['first_name' => 'Fanja', 'last_name' => 'Rakoto']);
    $other = $this->provisionEnrolledFamily($school, student: ['first_name' => 'Hery', 'last_name' => 'Rasoanaivo']);

    $classroom = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/classrooms", [
            'school_year_id' => $school['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ])->assertOk();

    $teacher = $this->provisionTeacher($school, $classroom['id']);

    $invoice = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/invoices")
        ->assertCreated()
        ->json('data');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/payments", [
            'invoice_id' => $invoice['id'],
            'amount' => 10_000,
            'method' => 'cash',
            'received_on' => '2026-08-26',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

    $schoolScore = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/reliability/school")
        ->assertOk()
        ->assertJsonPath('data.index_type', ReliabilityIndexes::SCHOOL)
        ->assertJsonPath('data.calculator_version', 'school-reliability.v1')
        ->assertJsonPath('data.displayable', true)
        ->json('data');

    expect($schoolScore['value'])->toBeGreaterThanOrEqual(70)
        ->and($schoolScore['factors'])->not->toBeEmpty()
        ->and(collect($schoolScore['factors'])->pluck('event_type'))->toContain('invoice_issued')
        ->and(collect($schoolScore['factors'])->pluck('event_type'))->toContain('payment_recorded');

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/reliability/school/compare")
        ->assertOk()
        ->assertJsonPath('data.digest_match', true)
        ->assertJsonPath('data.version_match', true);

    $familyId = (string) $family['family']->id;
    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/families/{$familyId}/reliability")
        ->assertOk()
        ->assertJsonPath('data.index_type', ReliabilityIndexes::FAMILY)
        ->assertJsonPath('data.calculator_version', FamilyReliabilityCalculator::VERSION);

    $relationship = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/families/{$familyId}/relationship")
        ->assertOk()
        ->json('data');

    expect($relationship['index_type'])->toBe(ReliabilityIndexes::RELATIONSHIP)
        ->and($relationship['band'])->toBe('insufficient')
        ->and($relationship['displayable'])->toBeFalse()
        ->and($relationship['value'])->toBeNull()
        ->and($relationship['event_count'])->toBeLessThan(5);

    TenantContext::run(TenantContext::forSchool($schoolId, $school['account']->person_id), function () use ($schoolId, $familyId): void {
        for ($i = 0; $i < 5; $i++) {
            TrustEvent::emit(
                ReliabilityIndexes::SUBJECT_RELATIONSHIP,
                $familyId,
                'message_delivered',
                $schoolId,
                'seed',
                (string) Str::uuid(),
            );
        }
        app(ComputeRelationshipHealth::class)->execute($schoolId, $familyId);
    });

    $enough = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/families/{$familyId}/relationship")
        ->assertOk()
        ->json('data');

    expect($enough['displayable'])->toBeTrue()
        ->and($enough['value'])->not->toBeNull()
        ->and($enough['band'])->not->toBe('insufficient');

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/families/{$familyId}/relationship/compare")
        ->assertOk()
        ->assertJsonPath('data.digest_match', true);

    $overview = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/reliability/overview")
        ->assertOk()
        ->json('data');

    expect($overview['school']['calculator_version'])->toBe('school-reliability.v1')
        ->and($overview['families'])->not->toBeEmpty();

    TenantContext::run(TenantContext::forSchool($schoolId, $school['account']->person_id), function () use ($schoolId, $familyId): void {
        ReliabilityScore::query()->create([
            'school_id' => $schoolId,
            'subject_type' => ReliabilityIndexes::SUBJECT_FAMILY,
            'subject_id' => $familyId,
            'index_type' => ReliabilityIndexes::FAMILY,
            'value' => 11,
            'band' => 'low',
            'calculator_version' => 'family-reliability.v0',
            'computed_at' => now(),
            'inputs_digest' => 'legacy',
            'event_count' => 0,
        ]);
        app(ComputeFamilyReliability::class)->execute($schoolId, $familyId);
        expect(
            ReliabilityScore::query()
                ->where('subject_id', $familyId)
                ->where('index_type', ReliabilityIndexes::FAMILY)
                ->count()
        )->toBe(2);
    });

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/reliability/school")
        ->assertNotFound();

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/families/{$familyId}/relationship")
        ->assertNotFound();

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/reliability/school")
        ->assertForbidden();

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/reliability/overview")
        ->assertForbidden();

    expect($other['family']->id)->not->toBe($familyId);
});

it('does not let muted print deliveries degrade relationship health', function () {
    $school = $this->provisionSchool();
    $this->provisionFeeSchedule($school);
    $family = $this->provisionEnrolledFamily($school);
    $schoolId = $school['school']->id;
    $familyId = (string) $family['family']->id;

    TenantContext::run(TenantContext::forSchool($schoolId, $school['account']->person_id), function () use ($schoolId, $familyId): void {
        foreach (range(1, 8) as $i) {
            TrustEvent::emit(
                ReliabilityIndexes::SUBJECT_RELATIONSHIP,
                $familyId,
                'message_uninstrumented',
                $schoolId,
                'message',
                (string) Str::uuid(),
                ['channel' => 'print', 'status' => 'unknown'],
            );
        }
        app(RecomputeCollection::class)->execute($schoolId, live: false);
    });

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/families/{$familyId}/relationship")
        ->assertOk()
        ->assertJsonPath('data.band', 'insufficient')
        ->assertJsonPath('data.value', null)
        ->assertJsonPath('data.event_count', 0)
        ->assertJsonPath('data.displayable', false);
});
