<?php

use App\Domain\Identity\Models\Person;
use App\Domain\Identity\PublicId\FanabePublicId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('regenerates on public_id collision instead of inserting a duplicate', function () {
    $existing = Person::factory()->create();

    $created = Person::createWithUniquePublicId([
        'public_id' => $existing->public_id,
        'first_name' => 'Haja',
        'last_name' => 'Randria',
    ]);

    expect($created->id)->not->toBe($existing->id)
        ->and($created->public_id)->not->toBe($existing->public_id)
        ->and(FanabePublicId::isValid($created->public_id))->toBeTrue();
});

it('rejects a raw duplicate insert at the database', function () {
    $existing = Person::factory()->create();

    expect(fn () => DB::transaction(fn () => Person::query()->create([
        'public_id' => $existing->public_id,
        'first_name' => 'Haja',
        'last_name' => 'Randria',
    ])))->toThrow(UniqueConstraintViolationException::class);
});
