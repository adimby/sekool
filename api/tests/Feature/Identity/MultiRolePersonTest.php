<?php

use App\Domain\Identity\Actions\AcquirePersonRole;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Models\PersonRole;

it('lets the same Person carry several roles at once', function () {
    $school = $this->provisionSchool();
    $person = $school['account']->person;

    app(AcquirePersonRole::class)->execute($person->id, PersonRoleType::Staff);
    app(AcquirePersonRole::class)->execute($person->id, PersonRoleType::Parent);

    $roles = PersonRole::query()->where('person_id', $person->id)->whereNull('ended_at')->pluck('role');

    expect($roles->map(fn ($r) => $r->value)->all())
        ->toContain(PersonRoleType::Staff->value)
        ->toContain(PersonRoleType::Parent->value);
});
