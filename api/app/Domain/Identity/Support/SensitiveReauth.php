<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\Cache;

final class SensitiveReauth
{
    public const TTL_SECONDS = 300;

    public static function grant(UserAccount $account): void
    {
        Cache::put(self::key($account->id), true, self::TTL_SECONDS);
    }

    public static function assert(UserAccount $account): void
    {
        if (! Cache::has(self::key($account->id))) {
            throw new DomainException('Confirmez votre identité pour continuer.', 403);
        }
    }

    public static function key(string $accountId): string
    {
        return 'fanabe.reauth.'.$accountId;
    }
}
