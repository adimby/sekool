<?php

use App\Domain\Identity\PublicId\FanabePublicId;

it('formats and round-trips the documented example', function () {
    $id = FanabePublicId::fromCanonical('7-48372196-P');

    expect($id->canonical())->toBe('748372196P')
        ->and($id->formatted())->toBe('7-48372196-P')
        ->and(FanabePublicId::checksumLetter('748372196'))->toBe('P');
});

it('rejects the illustrative letter from the specification (K)', function () {
    expect(FanabePublicId::isValid('7-48372196-K'))->toBeFalse();
});

it('detects every single-digit substitution in the payload', function () {
    $id = FanabePublicId::generate();
    $canonical = $id->canonical();
    $letter = $canonical[9];
    $digits = substr($canonical, 0, 9);

    for ($i = 1; $i < 9; $i++) {
        for ($d = 0; $d <= 9; $d++) {
            if ((string) $d === $digits[$i]) {
                continue;
            }
            $mutated = substr($digits, 0, $i).$d.substr($digits, $i + 1).$letter;
            expect(FanabePublicId::isValid($mutated))->toBeFalse();
        }
    }
});

it('detects transpositions of two payload digits', function () {
    $id = FanabePublicId::generate();
    $canonical = $id->canonical();
    $letter = $canonical[9];
    $digits = substr($canonical, 0, 9);

    for ($i = 1; $i < 9; $i++) {
        for ($j = $i + 1; $j < 9; $j++) {
            if ($digits[$i] === $digits[$j]) {
                continue;
            }
            $chars = str_split($digits);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
            expect(FanabePublicId::isValid(implode('', $chars).$letter))->toBeFalse();
        }
    }
});

it('never encodes personal data — two generations are independent', function () {
    $a = FanabePublicId::generate();
    $b = FanabePublicId::generate();

    expect($a->canonical())->not->toBe($b->canonical())
        ->and(strlen($a->canonical()))->toBe(10)
        ->and($a->canonical()[0])->toBe('7');
});
