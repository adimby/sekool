<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Consent\Actions\GrantConsent;
use App\Domain\Consent\Enums\ConsentScope;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Models\FamilyShareToken;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Support\SecretHash;
use App\Domain\Reliability\Models\TrustEvent;
use Illuminate\Support\Facades\DB;

final class RedeemFamilyShareToken
{
    /**
     * @return array{share: FamilyShareToken, linked_person_ids: list<string>}
     */
    public function execute(string $schoolId, string $actorPersonId, string $token): array
    {
        $share = FamilyShareToken::query()
            ->where('token_hash', SecretHash::make($token))
            ->first();

        if ($share === null || ! $share->isRedeemable()) {
            throw new DomainException('Lien parent invalide ou déjà utilisé.');
        }

        if ($share->target_school_id !== null && $share->target_school_id !== $schoolId) {
            throw new DomainException('Lien parent invalide ou déjà utilisé.');
        }

        return DB::transaction(function () use ($share, $schoolId, $actorPersonId): array {
            $grant = app(GrantSchoolPersonLink::class);

            $grant->execute(
                $schoolId,
                $share->created_by_person_id,
                SchoolPersonLinkKind::Parent,
                SchoolPersonLinkSource::ShareToken,
                grantsContactAccess: true,
            );

            $linked = [$share->created_by_person_id];

            foreach ($share->child_person_ids as $childId) {
                $grant->execute(
                    $schoolId,
                    $childId,
                    SchoolPersonLinkKind::Student,
                    SchoolPersonLinkSource::ShareToken,
                    grantsContactAccess: false,
                );
                $linked[] = $childId;

                foreach ($this->scopes($share->scopes) as $scope) {
                    app(GrantConsent::class)->execute(
                        subjectPersonId: $childId,
                        grantedByPersonId: $share->created_by_person_id,
                        granteeSchoolId: $schoolId,
                        scope: $scope,
                        purpose: 'Rédemption d\'un lien parent',
                        source: 'app',
                    );
                }
            }

            foreach ($this->scopes($share->scopes) as $scope) {
                if (in_array($scope, [ConsentScope::IdentityCore, ConsentScope::IdentityContact], true)) {
                    app(GrantConsent::class)->execute(
                        subjectPersonId: $share->created_by_person_id,
                        grantedByPersonId: $share->created_by_person_id,
                        granteeSchoolId: $schoolId,
                        scope: $scope,
                        purpose: 'Rédemption d\'un lien parent',
                        source: 'app',
                    );
                }
            }

            $share->forceFill([
                'redeemed_at' => now(),
                'redeemed_by_school_id' => $schoolId,
                'redeemed_by_person_id' => $actorPersonId,
            ])->save();

            Auditor::record('family.share_token_redeemed', 'family_share_token', $share->id, $share->created_by_person_id);
            TrustEvent::emit('person', $share->created_by_person_id, 'identity.linked', $schoolId, 'family_share_token', $share->id);

            return ['share' => $share->refresh(), 'linked_person_ids' => $linked];
        });
    }

    /**
     * @param  list<string>|null  $raw
     * @return list<ConsentScope>
     */
    private function scopes(?array $raw): array
    {
        $scopes = [];
        foreach ($raw ?? [] as $value) {
            $scope = ConsentScope::tryFrom($value);
            if ($scope !== null) {
                $scopes[] = $scope;
            }
        }

        return $scopes === [] ? [ConsentScope::IdentityCore, ConsentScope::IdentityContact] : $scopes;
    }
}
