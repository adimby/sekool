<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Models\IdentityMerge;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\PublicId\FanabePublicId;

final class IdentityMergePayload
{
    /**
     * @return array<string, mixed>
     */
    public static function row(IdentityMerge $merge): array
    {
        $merge->loadMissing(['surviving', 'duplicate']);

        return [
            'id' => $merge->id,
            'status' => $merge->status,
            'reason' => $merge->reason,
            'school_id' => $merge->school_id,
            'requested_at' => $merge->created_at?->toIso8601String(),
            'decided_at' => $merge->decided_at?->toIso8601String(),
            'surviving' => self::person($merge->surviving),
            'duplicate' => self::person($merge->duplicate),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function person(?Person $person): ?array
    {
        if ($person === null) {
            return null;
        }

        return [
            'id' => $person->id,
            'public_id' => FanabePublicId::fromCanonical($person->public_id)->formatted(),
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
        ];
    }
}
