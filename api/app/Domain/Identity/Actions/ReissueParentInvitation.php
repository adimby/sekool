<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\ParentInvitation;
use App\Domain\Identity\Models\Relationship;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Support\InvitationCode;
use App\Domain\Platform\Support\SecretHash;

final class ReissueParentInvitation
{
    public function execute(string $schoolId, string $personId, string $actorPersonId): string
    {
        if (UserAccount::query()->where('person_id', $personId)->exists()) {
            throw new DomainException('Ce parent a déjà un compte.');
        }

        $canAccessPortal = Relationship::query()
            ->where('subject_person_id', $personId)
            ->whereIn('type', [
                RelationshipType::ParentOf,
                RelationshipType::GuardianOf,
                RelationshipType::FinancialContactFor,
            ])
            ->where('status', 'active')
            ->exists();

        if (! $canAccessPortal) {
            throw new DomainException('Cette personne n’a pas d’accès à l’espace famille.');
        }

        ParentInvitation::query()
            ->where('person_id', $personId)
            ->whereNull('claimed_at')
            ->update(['expires_at' => now()->subMinute()]);

        $code = InvitationCode::generate();
        ParentInvitation::query()->create([
            'school_id' => $schoolId,
            'person_id' => $personId,
            'code_hash' => SecretHash::make($code),
            'created_by_person_id' => $actorPersonId,
            'expires_at' => now()->addDays(30),
        ]);

        Auditor::record('parent.invitation_reissued', 'parent_invitation', $personId, $personId);

        return $code;
    }
}
