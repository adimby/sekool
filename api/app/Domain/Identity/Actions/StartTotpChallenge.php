<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Identity\Support\Totp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class StartTotpChallenge
{
    public const TTL_SECONDS = 600;

    /**
     * @return array<string, mixed>
     */
    public function execute(UserAccount $account): array
    {
        $enroll = $account->totp_enabled_at === null || $account->totp_secret_encrypted === null;
        $secret = $enroll ? Totp::secret() : decrypt($account->totp_secret_encrypted);
        $challengeId = (string) Str::uuid();

        Cache::put(self::cacheKey($challengeId), [
            'account_id' => $account->id,
            'mode' => $enroll ? 'enroll' : 'verify',
            'secret' => $secret,
        ], self::TTL_SECONDS);

        $payload = [
            'challenge' => $enroll ? 'totp_enroll' : 'totp',
            'challenge_id' => $challengeId,
        ];

        if ($enroll) {
            $payload['secret'] = $secret;
            $payload['otpauth_uri'] = Totp::uri((string) $account->email, $secret);
        }

        if (app()->environment('local', 'testing')) {
            $payload['demo_code'] = Totp::code($secret);
        }

        return $payload;
    }

    public static function cacheKey(string $challengeId): string
    {
        return 'fanabe.totp.'.$challengeId;
    }
}
