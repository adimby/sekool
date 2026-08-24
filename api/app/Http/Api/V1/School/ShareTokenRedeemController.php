<?php

namespace App\Http\Api\V1\School;

use App\Domain\Identity\Actions\RedeemFamilyShareToken;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Identity\Support\PersonPayload;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShareTokenRedeemController extends Controller
{
    public function __invoke(Request $request, RedeemFamilyShareToken $redeem): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $result = $redeem->execute(
            TenantContext::requireSchoolId(),
            $request->user()->person_id,
            $data['token'],
        );

        $people = SchoolPersonLink::query()
            ->whereIn('person_id', $result['linked_person_ids'])
            ->with('person')
            ->get()
            ->map(fn (SchoolPersonLink $link) => PersonPayload::forSchool($link->person, $link))
            ->values();

        return response()->json(['data' => $people]);
    }
}
