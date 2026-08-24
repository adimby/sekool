<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\PersonLinkRequest;
use App\Domain\Identity\PublicId\FanabePublicId;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Support\SecretHash;
use InvalidArgumentException;

final class RequestPersonLinkByPublicId
{
    public const UNIFORM_MESSAGE = 'Si cette identité existe, le parent a été notifié.';

    public function execute(
        string $schoolId,
        string $actorPersonId,
        string $submittedPublicId,
        ?string $ip = null,
    ): PersonLinkRequest {
        $publicId = FanabePublicId::fromCanonical($submittedPublicId);

        $person = Person::findByPublicId($publicId->canonical());

        $request = PersonLinkRequest::query()->create([
            'school_id' => $schoolId,
            'submitted_public_id_hash' => SecretHash::publicId($publicId->canonical()),
            'matched_person_id' => $person?->id,
            'status' => 'pending',
            'requested_by_person_id' => $actorPersonId,
            'ip_hash' => SecretHash::ip($ip),
            'expires_at' => now()->addDays(7),
        ]);

        // Never record whether a match occurred — the audit log is visible to the school.
        Auditor::record('person_link.requested', 'person_link_request', $request->id, null, [
            'submitted_hash' => SecretHash::publicId($publicId->canonical()),
        ]);

        return $request;
    }

    public static function isFormatError(\Throwable $e): bool
    {
        return $e instanceof InvalidArgumentException
            && str_contains($e->getMessage(), 'FANABE public ID');
    }
}
