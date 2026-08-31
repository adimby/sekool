<?php

use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Certificate\Enums\CertificateType;
use App\Domain\Certificate\Support\CertificateCopy;
use App\Domain\Communication\Models\Message;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;

it('records a motif and justificatif and notifies the family once when a student is absent', function () {
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

    $date = now()->toDateString();

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $date,
            'session' => AttendanceSession::FullDay->value,
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Absent->value,
                'reason' => 'Maladie',
                'justification' => 'Certificat médical déposé à l’accueil',
                'client_reference' => 'aaaaaaaa-1111-4111-8111-111111111111',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.status', 'absent')
        ->assertJsonPath('data.0.reason', 'Maladie')
        ->assertJsonPath('data.0.justification', 'Certificat médical déposé à l’accueil');

    $this->actingAs($teacher['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/attendance?".http_build_query([
            'classroom_id' => $classroom['id'],
            'date' => $date,
            'session' => 'full_day',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.attendance.reason', 'Maladie')
        ->assertJsonPath('data.0.attendance.justification', 'Certificat médical déposé à l’accueil');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/attendance")
        ->assertOk()
        ->assertJsonPath('data.0.status', 'absent')
        ->assertJsonPath('data.0.reason', 'Maladie')
        ->assertJsonPath('data.0.justification', 'Certificat médical déposé à l’accueil');

    $inbox = $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson('/api/v1/parent/messages')
        ->assertOk()
        ->json('data');

    expect(collect($inbox)->pluck('template_key'))->toContain('same_day_absence')
        ->and(mb_strtolower(json_encode($inbox)))->not->toContain('score')
        ->and(mb_strtolower(json_encode($inbox)))->not->toContain('risque');

    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/attendance", [
            'date' => $date,
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => 'absent',
                'reason' => 'Maladie',
            ]],
        ])
        ->assertCreated();

    TenantContext::runWithRlsBypass(function () use ($family): void {
        expect(Message::query()->withoutGlobalScopes()->where('template_key', 'same_day_absence')->where('channel', 'in_app')->count())->toBe(1)
            ->and(AttendanceRecord::query()->withoutGlobalScopes()->where('enrollment_id', $family['enrollment']->id)->count())->toBe(1);
    });
});

it('withdraws a student and issues a verifiable radiation certificate', function () {
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

    $withdrawn = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/withdraw", [
            'reason' => 'Déménagement',
        ])
        ->assertOk()
        ->json('data');

    expect($withdrawn['status'])->toBe(EnrollmentStatus::Withdrawn->value)
        ->and($withdrawn['exit_reason'])->toBe('Déménagement')
        ->and($withdrawn['certificate']['type'])->toBe(CertificateType::Withdrawal->value)
        ->and($withdrawn['certificate']['type_label'])->toBe('Certificat de radiation')
        ->and($withdrawn['certificate']['token'])->toHaveLength(40);

    TenantContext::runWithRlsBypass(function () use ($family, $withdrawn): void {
        $enrollment = Enrollment::query()->withoutGlobalScopes()->find($family['enrollment']->id);
        expect($enrollment?->status)->toBe(EnrollmentStatus::Withdrawn);

        $stored = Storage::disk('local')->get(
            'schools/'.$enrollment->school_id.'/certificates/'.$withdrawn['certificate']['public_reference'].'.html',
        );
        expect($stored)->toContain('Certificat de radiation')
            ->and($stored)->toContain('Déménagement')
            ->and($stored)->toContain(CertificateCopy::DISCLAIMER);
    });

    $this->getJson('/api/v1/verify/certificates/'.$withdrawn['certificate']['token'])
        ->assertOk()
        ->assertJsonPath('status', 'VALID');

    $this->actingAs($family['parentAccount'], 'sanctum')
        ->getJson("/api/v1/parent/children/{$family['student']->id}/certificates")
        ->assertOk()
        ->assertJsonPath('data.0.type_label', 'Certificat de radiation');

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/withdraw", [
            'reason' => 'Encore',
        ])
        ->assertStatus(422);

    $teacher = $this->provisionTeacher($school, $classroom['id']);
    $this->actingAs($teacher['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/enrollments/{$family['enrollment']->id}/withdraw", [
            'reason' => 'Non',
        ])
        ->assertForbidden();
});

it('records an excused day with a motif and does not notify the family', function () {
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
            'session' => 'full_day',
            'records' => [[
                'enrollment_id' => $family['enrollment']->id,
                'status' => AttendanceStatus::Excused->value,
                'reason' => 'Raison familiale',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.status', 'excused')
        ->assertJsonPath('data.0.reason', 'Raison familiale');

    TenantContext::runWithRlsBypass(function (): void {
        expect(Message::query()->withoutGlobalScopes()->where('template_key', 'same_day_absence')->count())->toBe(0);
    });
});

it('does not let school B radiate an enrollment of school A', function () {
    $a = $this->provisionSchool();
    $b = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($a);
    $core = $this->provisionFeeSchedule($a);
    $schoolA = $a['school']->id;
    $schoolB = $b['school']->id;

    $classroom = $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolA}/classrooms", [
            'school_year_id' => $a['year']->id,
            'grade_level_id' => $core['grade']->id,
            'name' => '6ème A',
        ])->json('data');

    $this->actingAs($a['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolA}/enrollments/{$family['enrollment']->id}/assign-classroom", [
            'classroom_id' => $classroom['id'],
        ]);

    $this->actingAs($b['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolB}/enrollments/{$family['enrollment']->id}/withdraw", [
            'reason' => 'Intrusion',
        ])
        ->assertNotFound();

    $this->actingAs($b['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolA}/enrollments/{$family['enrollment']->id}/withdraw", [
            'reason' => 'Intrusion',
        ])
        ->assertNotFound();

    TenantContext::runWithRlsBypass(function () use ($family): void {
        $enrollment = Enrollment::query()->withoutGlobalScopes()->find($family['enrollment']->id);
        expect($enrollment?->status)->toBe(EnrollmentStatus::Active);
    });
});
