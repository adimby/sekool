<?php

use App\Domain\Academic\Actions\DetectStudentAlerts;
use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Support\AlertCopy;
use App\Domain\Communication\Support\MessageCatalog;
use App\Domain\Platform\Tenancy\TenantContext;
use Carbon\Carbon;

it('opens a neutral early-warning alert that a human must acknowledge', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'Indian/Antananarivo'));

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

    foreach ([0, 1, 2] as $daysAgo) {
        $this->actingAs($teacher['account'], 'sanctum')
            ->postJson("/api/v1/schools/{$schoolId}/attendance", [
                'date' => Carbon::now('Indian/Antananarivo')->subDays($daysAgo)->toDateString(),
                'session' => AttendanceSession::FullDay->value,
                'records' => [[
                    'enrollment_id' => $family['enrollment']->id,
                    'status' => AttendanceStatus::Absent->value,
                ]],
            ])
            ->assertCreated();
    }

    $cockpit = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/cockpit")
        ->assertOk()
        ->json();

    expect($cockpit['attention'])->not->toBeEmpty()
        ->and($cockpit['attention'][0]['reason_summary'])->toBe(AlertCopy::summary(\App\Domain\Academic\Enums\StudentAlertCategory::AbsenceIncrease))
        ->and($cockpit['attention'][0]['reason_summary'])->not->toContain('élève en difficulté');

    foreach (MessageCatalog::forbiddenFamilyTerms() as $term) {
        expect(mb_strtolower($cockpit['attention'][0]['reason_summary']))->not->toContain(mb_strtolower($term));
    }

    $alertId = $cockpit['attention'][0]['id'];

    $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/alerts/{$alertId}/acknowledge")
        ->assertOk()
        ->assertJsonPath('data.status', 'acknowledged');

    $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/cockpit")
        ->assertOk()
        ->assertJsonCount(0, 'attention');

    TenantContext::activate(TenantContext::forSchool($schoolId, $school['account']->person_id));
    expect(app(DetectStudentAlerts::class)->execute())->toHaveCount(0);
    TenantContext::clear();

    Carbon::setTestNow();
});

it('opens a grades-decline alert when notes drop by four points', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'Indian/Antananarivo'));

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
    $subjectId = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/subjects", ['name' => 'Mathématiques'])
        ->json('data.id');

    foreach ([['2026-08-10', 14], ['2026-08-24', 8]] as [$date, $value]) {
        $this->actingAs($teacher['account'], 'sanctum')
            ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/grades", [
                'enrollment_id' => $family['enrollment']->id,
                'subject_id' => $subjectId,
                'value' => $value,
                'assessed_on' => $date,
            ])
            ->assertCreated();
    }

    $cockpit = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/cockpit")
        ->assertOk()
        ->json();

    expect($cockpit['attention'])->not->toBeEmpty()
        ->and($cockpit['attention'][0]['category'])->toBe('grades_decline')
        ->and($cockpit['attention'][0]['reason_summary'])->toBe(AlertCopy::summary(\App\Domain\Academic\Enums\StudentAlertCategory::GradesDecline))
        ->and($cockpit['attention'][0]['detector_version'])->toBe('ew-v2');

    foreach (MessageCatalog::forbiddenFamilyTerms() as $term) {
        expect(mb_strtolower($cockpit['attention'][0]['reason_summary']))->not->toContain(mb_strtolower($term));
    }

    Carbon::setTestNow();
});

it('opens a homework-decline alert when notes drop and two homeworks exist', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00', 'Indian/Antananarivo'));

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
    $subjectId = $this->actingAs($school['account'], 'sanctum')
        ->postJson("/api/v1/schools/{$schoolId}/subjects", ['name' => 'Malagasy'])
        ->json('data.id');

    foreach ([1, 2] as $n) {
        $this->actingAs($teacher['account'], 'sanctum')
            ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/posts", [
                'kind' => 'homework',
                'title' => "Devoir {$n}",
                'body' => 'Exercices à faire.',
                'due_on' => Carbon::now('Indian/Antananarivo')->addDays($n)->toDateString(),
            ])
            ->assertCreated();
    }

    foreach ([['2026-08-10', 15], ['2026-08-24', 9]] as [$date, $value]) {
        $this->actingAs($teacher['account'], 'sanctum')
            ->postJson("/api/v1/schools/{$schoolId}/classrooms/{$classroom['id']}/grades", [
                'enrollment_id' => $family['enrollment']->id,
                'subject_id' => $subjectId,
                'value' => $value,
                'assessed_on' => $date,
            ])
            ->assertCreated();
    }

    $cockpit = $this->actingAs($school['account'], 'sanctum')
        ->getJson("/api/v1/schools/{$schoolId}/cockpit")
        ->assertOk()
        ->json();

    expect($cockpit['attention'])->not->toBeEmpty()
        ->and($cockpit['attention'][0]['category'])->toBe('homework_decline')
        ->and($cockpit['attention'][0]['reason_summary'])->not->toContain('élève en difficulté');

    foreach (MessageCatalog::forbiddenFamilyTerms() as $term) {
        expect(mb_strtolower($cockpit['attention'][0]['reason_summary']))->not->toContain(mb_strtolower($term));
    }

    Carbon::setTestNow();
});

it('keeps early-warning copy free of judgment', function () {
    foreach (\App\Domain\Academic\Enums\StudentAlertCategory::cases() as $category) {
        $copy = mb_strtolower(AlertCopy::summary($category));
        expect($copy)->toContain('évolution inhabituelle')
            ->and($copy)->not->toContain('élève en difficulté')
            ->and($copy)->not->toContain('mauvais');
    }
});
