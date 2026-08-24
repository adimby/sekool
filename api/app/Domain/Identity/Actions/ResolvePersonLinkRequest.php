<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Models\PersonLinkRequest;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\Reliability\Models\TrustEvent;
use Illuminate\Support\Facades\DB;

final class ResolvePersonLinkRequest
{
    public function approve(string $requestId, string $actorPersonId): PersonLinkRequest
    {
        return $this->resolve($requestId, $actorPersonId, approved: true);
    }

    public function refuse(string $requestId, string $actorPersonId): PersonLinkRequest
    {
        return $this->resolve($requestId, $actorPersonId, approved: false);
    }

    private function resolve(string $requestId, string $actorPersonId, bool $approved): PersonLinkRequest
    {
        $request = TenantContext::runWithRlsBypass(fn (): ?PersonLinkRequest => PersonLinkRequest::query()
            ->withoutGlobalScopes()
            ->find($requestId));

        if ($request === null || $request->status !== 'pending') {
            throw new DomainException('Demande introuvable.', 404);
        }

        if ($request->expires_at->isPast()) {
            $request->forceFill(['status' => 'expired', 'resolved_at' => now()])->save();
            throw new DomainException('Demande introuvable.', 404);
        }

        $matchedId = $request->matched_person_id;
        if ($matchedId === null) {
            throw new DomainException('Demande introuvable.', 404);
        }

        $actorIsMatch = $matchedId === $actorPersonId;
        $actorIsParent = ParentAuthorization::isLegalGuardianOf($actorPersonId, $matchedId);

        if (! $actorIsMatch && ! $actorIsParent) {
            throw new DomainException('Demande introuvable.', 404);
        }

        return TenantContext::runWithRlsBypass(function () use ($request, $actorPersonId, $approved, $matchedId, $actorIsMatch): PersonLinkRequest {
            return DB::transaction(function () use ($request, $actorPersonId, $approved, $matchedId, $actorIsMatch): PersonLinkRequest {
                $request->forceFill([
                    'status' => $approved ? 'approved' : 'denied',
                    'resolved_at' => now(),
                ])->save();

                if ($approved) {
                    $grant = app(GrantSchoolPersonLink::class);
                    $parentId = $actorIsMatch ? $actorPersonId : $actorPersonId;
                    $grant->execute(
                        $request->school_id,
                        $parentId,
                        SchoolPersonLinkKind::Parent,
                        SchoolPersonLinkSource::PublicIdApproved,
                        grantsContactAccess: true,
                    );

                    if (! $actorIsMatch) {
                        $grant->execute(
                            $request->school_id,
                            $matchedId,
                            SchoolPersonLinkKind::Student,
                            SchoolPersonLinkSource::PublicIdApproved,
                            grantsContactAccess: false,
                        );
                    }

                    TrustEvent::emit('person', $parentId, 'identity.linked', $request->school_id, 'person_link_request', $request->id);
                }

                Auditor::record(
                    $approved ? 'person_link.approved' : 'person_link.denied',
                    'person_link_request',
                    $request->id,
                    $actorPersonId,
                );

                return $request->refresh();
            });
        });
    }
}
