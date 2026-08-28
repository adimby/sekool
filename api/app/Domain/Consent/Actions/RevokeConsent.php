<?php

namespace App\Domain\Consent\Actions;

use App\Domain\Consent\Models\Consent;
use App\Domain\Consent\Models\ConsentEvent;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Reliability\Models\TrustEvent;

final class RevokeConsent
{
    public function execute(string $consentId, string $actorPersonId): Consent
    {
        $consent = Consent::query()->find($consentId);

        if ($consent === null) {
            throw new DomainException('Consentement introuvable.', 404);
        }

        if ($consent->granted_by_person_id !== $actorPersonId && $consent->subject_person_id !== $actorPersonId) {
            throw new DomainException('Consentement introuvable.', 404);
        }

        if ($consent->revoked_at !== null) {
            return $consent;
        }

        $consent->forceFill(['revoked_at' => now()])->save();

        ConsentEvent::query()->create([
            'consent_id' => $consent->id,
            'event' => 'revoked',
            'occurred_at' => now(),
            'actor_person_id' => $actorPersonId,
        ]);

        Auditor::record('consent.revoked', 'consent', $consent->id, $consent->subject_person_id);
        TrustEvent::emit('person', $consent->subject_person_id, 'consent.revoked', $consent->grantee_school_id, 'consent', $consent->id);

        return $consent->refresh();
    }
}
