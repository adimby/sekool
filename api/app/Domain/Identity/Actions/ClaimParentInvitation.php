<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\ParentInvitation;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Support\SecretHash;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class ClaimParentInvitation
{
    /**
     * @return array{account: UserAccount, token: string}
     */
    public function execute(string $code, string $email, string $password): array
    {
        return TenantContext::runWithRlsBypass(function () use ($code, $email, $password): array {
            $invitation = ParentInvitation::query()
                ->withoutGlobalScopes()
                ->where('code_hash', SecretHash::make($code))
                ->first();

            if (
                $invitation === null
                || $invitation->claimed_at !== null
                || $invitation->expires_at->isPast()
            ) {
                throw new DomainException("Code d'invitation invalide.");
            }

            if (UserAccount::query()->where('person_id', $invitation->person_id)->exists()) {
                throw new DomainException("Code d'invitation invalide.");
            }

            return DB::transaction(function () use ($invitation, $email, $password): array {
                $invitation->forceFill(['claimed_at' => now()])->save();

                $account = UserAccount::query()->create([
                    'person_id' => $invitation->person_id,
                    'email' => $email,
                    'password' => $password,
                    'must_change_password' => false,
                ]);

                Auditor::record('parent.invitation_claimed', 'parent_invitation', $invitation->id, $invitation->person_id);

                $token = $account->createToken('api')->plainTextToken;

                return compact('account', 'token');
            });
        });
    }
}
