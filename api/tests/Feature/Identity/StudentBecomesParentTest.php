<?php

use App\Domain\Identity\Actions\AcquirePersonRole;
use App\Domain\Identity\Actions\EstablishRelationship;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\Person;

it('lets a student become a parent without changing identity', function () {
    $school = $this->provisionSchool();
    $family = $this->provisionEnrolledFamily($school);

    $formerStudentId = $family['student']->id;

    app(AcquirePersonRole::class)->close($formerStudentId, PersonRoleType::Student, now()->subYears(2));
    app(AcquirePersonRole::class)->execute($formerStudentId, PersonRoleType::Alumni, now()->subYears(2));
    app(AcquirePersonRole::class)->execute($formerStudentId, PersonRoleType::Parent);

    $child = Person::createWithUniquePublicId([
        'first_name' => 'Niry',
        'last_name' => $family['student']->last_name,
        'birth_date' => '2020-01-15',
    ]);
    app(EstablishRelationship::class)->execute(
        $formerStudentId,
        $child->id,
        RelationshipType::ParentOf,
    );

    $fresh = $family['student']->fresh();

    expect($fresh->id)->toBe($formerStudentId)
        ->and($fresh->public_id)->toBe($family['student']->public_id);

    $roles = $fresh->roles()->get();
    expect($roles->pluck('role')->map->value)->toContain(PersonRoleType::Student->value)
        ->and($roles->pluck('role')->map->value)->toContain(PersonRoleType::Parent->value)
        ->and($roles->firstWhere('role', PersonRoleType::Student)->ended_at)->not->toBeNull()
        ->and($roles->firstWhere('role', PersonRoleType::Parent)->ended_at)->toBeNull();
});
