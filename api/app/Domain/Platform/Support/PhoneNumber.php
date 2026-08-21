<?php

namespace App\Domain\Platform\Support;

use InvalidArgumentException;
use Stringable;

/**
 * E.164 phone numbers. Madagascar default country code +261.
 */
final class PhoneNumber implements Stringable
{
    public const DEFAULT_COUNTRY_CODE = '261';

    private function __construct(private readonly string $e164) {}

    public static function parse(string $raw, string $defaultCountryCode = self::DEFAULT_COUNTRY_CODE): self
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Phone number is empty.');
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($raw, '+')) {
            $e164 = '+'.$digits;
        } elseif (str_starts_with($digits, $defaultCountryCode)) {
            $e164 = '+'.$digits;
        } elseif (str_starts_with($digits, '0')) {
            $e164 = '+'.$defaultCountryCode.substr($digits, 1);
        } else {
            $e164 = '+'.$defaultCountryCode.$digits;
        }

        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $e164)) {
            throw new InvalidArgumentException('Invalid E.164 phone number.');
        }

        return new self($e164);
    }

    public function e164(): string
    {
        return $this->e164;
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
