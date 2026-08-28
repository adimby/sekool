<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\Relationship;
use App\Domain\Platform\Exceptions\DomainException;

final class EstablishRelationship
{
    /**
     * @param  list<string>|null  $scopes
     */
    public function execute(
        string $subjectPersonId,
        string $objectPersonId,
        RelationshipType $type,
        ?array $scopes = null,
        string $verificationMethod = 'family_approved',
        ?string $verifiedByPersonId = null,
    ): Relationship {
        if ($subjectPersonId === $objectPersonId) {
            throw new DomainException('Une personne ne peut pas être en relation avec elle-même.');
        }

        $existing = Relationship::query()
            ->where('subject_person_id', $subjectPersonId)
            ->where('object_person_id', $objectPersonId)
            ->where('type', $type)
            ->where('status', 'active')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Relationship::query()->create([
            'subject_person_id' => $subjectPersonId,
            'object_person_id' => $objectPersonId,
            'type' => $type,
            'scopes' => $scopes,
            'status' => 'active',
            'verification_method' => $verificationMethod,
            'verified_by_person_id' => $verifiedByPersonId,
            'established_at' => now(),
        ]);
    }
}
