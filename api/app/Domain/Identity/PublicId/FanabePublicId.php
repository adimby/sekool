<?php

namespace App\Domain\Identity\PublicId;

use InvalidArgumentException;
use Stringable;

/**
 * FANABE Person ID: 7-XXXXXXXX-C (canonical form without hyphens: 7XXXXXXXXC).
 *
 * Namespace "7" + 8 random digits + checksum letter over an alphabet of 23
 * (I, O, Q removed). The checksum is N mod 23 of the 9 digits. It detects
 * transcription errors; it is not uniqueness and not authentication.
 */
final class FanabePublicId implements Stringable
{
    public const NAMESPACE_DIGIT = '7';

    public const ALPHABET = 'ABCDEFGHJKLMNPRSTUVWXYZ';

    public const PAYLOAD_LENGTH = 8;

    public const MAX_GENERATION_ATTEMPTS = 5;

    private function __construct(private readonly string $canonical) {}

    public static function generate(): self
    {
        $payload = '';
        for ($i = 0; $i < self::PAYLOAD_LENGTH; $i++) {
            $payload .= (string) random_int(0, 9);
        }

        $nine = self::NAMESPACE_DIGIT.$payload;

        return new self($nine.self::checksumLetter($nine));
    }

    public static function fromCanonical(string $value): self
    {
        $normalized = strtoupper(str_replace(['-', ' ', '_'], '', $value));

        if (! preg_match('/^7[0-9]{8}['.self::ALPHABET.']$/', $normalized)) {
            throw new InvalidArgumentException('Invalid FANABE public ID format.');
        }

        $nine = substr($normalized, 0, 9);
        $letter = substr($normalized, 9, 1);

        if ($letter !== self::checksumLetter($nine)) {
            throw new InvalidArgumentException('Invalid FANABE public ID checksum.');
        }

        return new self($normalized);
    }

    public static function isValid(string $value): bool
    {
        try {
            self::fromCanonical($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function checksumLetter(string $nineDigits): string
    {
        if (! preg_match('/^7[0-9]{8}$/', $nineDigits)) {
            throw new InvalidArgumentException('Checksum input must be namespace 7 plus 8 digits.');
        }

        $mod = ((int) $nineDigits) % strlen(self::ALPHABET);

        return self::ALPHABET[$mod];
    }

    public function canonical(): string
    {
        return $this->canonical;
    }

    public function formatted(): string
    {
        return sprintf(
            '%s-%s-%s',
            $this->canonical[0],
            substr($this->canonical, 1, 8),
            $this->canonical[9],
        );
    }

    public function __toString(): string
    {
        return $this->canonical;
    }
}
