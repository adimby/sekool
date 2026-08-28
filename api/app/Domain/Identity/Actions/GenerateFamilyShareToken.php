<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\FamilyShareToken;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Support\SecretHash;

final class GenerateFamilyShareToken
{
    /**
     * @param  list<string>  $childPersonIds
     * @param  list<string>  $scopes
     * @return array{token: string, share: FamilyShareToken}
     */
    public function execute(
        string $createdByPersonId,
        array $childPersonIds,
        array $scopes = ['identity.core', 'identity.contact'],
        ?string $targetSchoolId = null,
        int $ttlDays = 7,
    ): array {
        if ($childPersonIds === []) {
            throw new DomainException('Le lien doit porter sur au moins un enfant.');
        }

        foreach ($childPersonIds as $childId) {
            if (! ParentAuthorization::isLegalGuardianOf($createdByPersonId, $childId)) {
                throw new DomainException('Vous ne pouvez partager que les enfants que vous êtes autorisé à voir.', 403);
            }
        }

        $plaintext = bin2hex(random_bytes(20));

        $share = FamilyShareToken::query()->create([
            'created_by_person_id' => $createdByPersonId,
            'token_hash' => SecretHash::make($plaintext),
            'child_person_ids' => array_values($childPersonIds),
            'scopes' => $scopes,
            'target_school_id' => $targetSchoolId,
            'expires_at' => now()->addDays($ttlDays),
        ]);

        Auditor::record('family.share_token_issued', 'family_share_token', $share->id, $createdByPersonId, [
            'child_count' => count($childPersonIds),
        ]);

        return ['token' => $plaintext, 'share' => $share];
    }
}
