<?php

namespace App\Domain\Consent\Actions;

use App\Domain\Consent\Enums\ConsentScope;
use App\Domain\Consent\Models\Consent;
use App\Domain\Consent\Models\ConsentEvent;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Reliability\Models\TrustEvent;

final class GrantConsent
{
    public function execute(
        string $subjectPersonId,
        string $grantedByPersonId,
        string $granteeSchoolId,
        ConsentScope $scope,
        string $purpose,
        string $source = 'app',
        int $ttlMonths = 12,
    ): Consent {
        $existing = Consent::query()
            ->where('subject_person_id', $subjectPersonId)
            ->where('grantee_school_id', $granteeSchoolId)
            ->where('scope', $scope)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $consent = Consent::query()->create([
            'subject_person_id' => $subjectPersonId,
            'granted_by_person_id' => $grantedByPersonId,
            'grantee_school_id' => $granteeSchoolId,
            'scope' => $scope,
            'purpose' => $purpose,
            'granted_at' => now(),
            'expires_at' => now()->addMonths($ttlMonths),
            'source' => $source,
            'terms_version' => '1',
        ]);

        ConsentEvent::query()->create([
            'consent_id' => $consent->id,
            'event' => 'granted',
            'occurred_at' => now(),
            'actor_person_id' => $grantedByPersonId,
        ]);

        Auditor::record('consent.granted', 'consent', $consent->id, $subjectPersonId, [
            'scope' => $scope->value,
            'grantee_school_id' => $granteeSchoolId,
        ]);
        TrustEvent::emit('person', $subjectPersonId, 'consent.granted', $granteeSchoolId, 'consent', $consent->id, [
            'scope' => $scope->value,
        ]);

        return $consent;
    }
}
