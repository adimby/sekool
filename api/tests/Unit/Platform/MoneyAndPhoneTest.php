<?php

use App\Domain\Platform\Support\Money;
use App\Domain\Platform\Support\PhoneNumber;

it('adds and subtracts Ariary as integers', function () {
    $sum = Money::of(180_000)->plus(Money::of(20_000));

    expect($sum->amount)->toBe(200_000)
        ->and($sum->currency())->toBe('MGA');
});

it('refuses a negative amount', function () {
    Money::of(-1);
})->throws(InvalidArgumentException::class);

it('normalizes Malagasy numbers to E.164', function () {
    expect((string) PhoneNumber::parse('034 12 345 67'))->toBe('+261341234567')
        ->and((string) PhoneNumber::parse('+261341234567'))->toBe('+261341234567');
});
