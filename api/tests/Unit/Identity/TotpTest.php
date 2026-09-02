<?php

use App\Domain\Identity\Support\Totp;

it('matches the RFC 6238 SHA-1 6-digit vector', function () {
    $secret = Totp::base32Encode('12345678901234567890');

    expect(Totp::code($secret, 59))->toBe('287082')
        ->and(Totp::verify($secret, '287082', 0, 59))->toBeTrue()
        ->and(Totp::verify($secret, '000000', 0, 59))->toBeFalse();
});

it('accepts a neighbouring window and rejects a far code', function () {
    $secret = Totp::secret();
    $now = 1_700_000_000;
    $code = Totp::code($secret, $now - 30);

    expect(Totp::verify($secret, $code, 1, $now))->toBeTrue()
        ->and(Totp::verify($secret, $code, 0, $now))->toBeFalse();
});
