<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Consent\Actions\GrantConsent;
use App\Domain\Consent\Actions\RevokeConsent;
use App\Domain\Consent\Enums\ConsentScope;
use App\Domain\Consent\Models\Consent;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\School;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConsentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $parentId = $request->user()->person_id;
        $subjectIds = array_merge([$parentId], ParentAuthorization::authorizedChildIds($parentId));

        $rows = Consent::query()->whereIn('subject_person_id', $subjectIds)->orderByDesc('granted_at')->get();
        $schools = School::query()->whereIn('id', $rows->pluck('grantee_school_id'))->get()->keyBy('id');

        return response()->json([
            'data' => $rows->map(fn (Consent $row): array => [
                'id' => $row->id,
                'subject_person_id' => $row->subject_person_id,
                'grantee_school_id' => $row->grantee_school_id,
                'school_name' => $schools->get($row->grantee_school_id)?->name,
                'scope' => $row->scope instanceof \BackedEnum ? $row->scope->value : (string) $row->scope,
                'purpose' => $row->purpose,
                'granted_at' => $row->granted_at?->toIso8601String(),
                'expires_at' => $row->expires_at?->toIso8601String(),
                'revoked_at' => $row->revoked_at?->toIso8601String(),
                'active' => $row->isActive(),
            ])->values(),
        ]);
    }

    public function store(Request $request, GrantConsent $grant): JsonResponse
    {
        $data = $request->validate([
            'subject_person_id' => ['required', 'uuid'],
            'grantee_school_id' => ['required', 'uuid'],
            'scope' => ['required', 'string'],
            'purpose' => ['required', 'string', 'max:500'],
        ]);

        $parentId = $request->user()->person_id;
        if ($data['subject_person_id'] !== $parentId
            && ! ParentAuthorization::isLegalGuardianOf($parentId, $data['subject_person_id'])) {
            throw new DomainException('Not found.', 404);
        }

        $scope = ConsentScope::tryFrom($data['scope']);
        if ($scope === null) {
            throw new DomainException('Portée de consentement inconnue.');
        }

        $consent = $grant->execute(
            $data['subject_person_id'],
            $parentId,
            $data['grantee_school_id'],
            $scope,
            $data['purpose'],
        );

        return response()->json(['data' => $consent], 201);
    }

    public function revoke(Request $request, string $consent, RevokeConsent $revoke): JsonResponse
    {
        $row = $revoke->execute($consent, $request->user()->person_id);

        return response()->json(['data' => $row]);
    }
}
