<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Identity\Support\Totp;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

final class CompleteTotpChallenge
{
    public function execute(string $challengeId, string $code): UserAccount
    {
        $payload = Cache::get(StartTotpChallenge::cacheKey($challengeId));
        if (! is_array($payload) || ! isset($payload['account_id'], $payload['secret'], $payload['mode'])) {
            throw new DomainException('Session TOTP expirée. Reconnectez-vous.', 422);
        }

        $account = TenantContext::runWithRlsBypass(
            fn (): ?UserAccount => UserAccount::query()->find($payload['account_id']),
        );
        if ($account === null) {
            throw new DomainException('Session TOTP expirée. Reconnectez-vous.', 422);
        }

        if (! Totp::verify((string) $payload['secret'], $code)) {
            TenantContext::runWithRlsBypass(function () use ($account): void {
                $account->forceFill([
                    'failed_attempts' => (int) $account->failed_attempts + 1,
                ])->save();
                Auditor::record('auth.totp.failed', 'user_account', $account->id, $account->person_id, [], 'denied');
            });
            throw new DomainException('Code TOTP invalide.', 422);
        }

        TenantContext::runWithRlsBypass(function () use ($account, $payload): void {
            $updates = [
                'failed_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
            ];
            if ($payload['mode'] === 'enroll') {
                $updates['totp_secret_encrypted'] = encrypt($payload['secret']);
                $updates['totp_enabled_at'] = now();
            }
            $account->forceFill($updates)->save();
            Auditor::record(
                $payload['mode'] === 'enroll' ? 'auth.totp.enrolled' : 'auth.login',
                'user_account',
                $account->id,
                $account->person_id,
            );
        });

        Cache::forget(StartTotpChallenge::cacheKey($challengeId));

        return $account->fresh() ?? $account;
    }
}
