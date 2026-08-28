<?php

namespace App\Domain\Platform\Support;

/**
 * Human-typable invitation code. Unambiguous alphabet (no 0/O, 1/I/L).
 */
final class InvitationCode
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 8;

    public static function generate(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
