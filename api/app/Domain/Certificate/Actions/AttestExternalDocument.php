<?php

namespace App\Domain\Certificate\Actions;

use App\Domain\Identity\Models\FanabeDocument;
use App\Domain\Identity\Models\DocumentVerificationEvent;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class AttestExternalDocument
{
    public function execute(string $schoolId, string $documentId, string $actorPersonId): FanabeDocument
    {
        $document = FanabeDocument::query()->find($documentId);
        if ($document === null) {
            throw new DomainException('Document introuvable.', 404);
        }
        if (! $document->isExternal()) {
            throw new DomainException('Seuls les documents externes peuvent être attestés.');
        }

        $from = $document->verification_status;
        $document->forceFill([
            'verification_status' => 'attested_by_school',
            'issuer_school_id' => $document->issuer_school_id,
        ])->save();

        DocumentVerificationEvent::query()->create([
            'document_id' => $document->id,
            'school_id' => $schoolId,
            'from_status' => $from,
            'to_status' => 'attested_by_school',
            'actor_person_id' => $actorPersonId,
            'actor_school_id' => $schoolId,
            'method' => 'staff_attested',
            'occurred_at' => now(),
        ]);

        Auditor::record('document.attested', 'document', $document->id, $document->owner_person_id);

        return $document->fresh() ?? $document;
    }
}
