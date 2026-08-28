<?php

namespace App\Domain\Platform\Audit;

use App\Domain\Platform\Tenancy\TenantContext;

final class Auditor
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?string $subjectPersonId = null,
        array $context = [],
        string $outcome = 'allowed',
    ): AuditEvent {
        $tenant = TenantContext::current();

        return AuditEvent::record([
            'actor_person_id' => $tenant?->personId,
            'actor_school_id' => $tenant?->schoolId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'subject_person_id' => $subjectPersonId,
            'context' => $context,
            'outcome' => $outcome,
        ]);
    }
}
