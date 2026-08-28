<?php

namespace App\Domain\Platform\Support;

/**
 * One-way hashes for capability secrets (share tokens, invitation codes) and
 * for guessable identifiers stored on request rows (public ID, IP).
 */
final class SecretHash
{
    public static function make(string $plaintext): string
    {
        return hash('sha256', strtoupper($plaintext));
    }

    public static function publicId(string $canonical): string
    {
        return hash_hmac('sha256', strtoupper($canonical), (string) config('app.key'));
    }

    public static function ip(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    public static function equals(string $plaintext, string $hash): bool
    {
        return hash_equals($hash, self::make($plaintext));
    }
}
