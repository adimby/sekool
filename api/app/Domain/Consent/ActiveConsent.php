<?php

namespace App\Domain\Consent;

use App\Domain\Consent\Enums\ConsentScope;
use App\Domain\Consent\Models\Consent;

final class ActiveConsent
{
    public static function exists(string $subjectPersonId, string $granteeSchoolId, ConsentScope $scope): bool
    {
        return Consent::query()
            ->where('subject_person_id', $subjectPersonId)
            ->where('grantee_school_id', $granteeSchoolId)
            ->where('scope', $scope)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
