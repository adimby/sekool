<?php

namespace App\Http\Api\V1\School;

use App\Domain\Identity\Actions\RequestPersonLinkByPublicId;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class PersonLinkRequestController extends Controller
{
    public function store(Request $request, RequestPersonLinkByPublicId $action): JsonResponse
    {
        $data = $request->validate([
            'public_id' => ['required', 'string'],
        ]);

        try {
            $action->execute(
                TenantContext::requireSchoolId(),
                $request->user()->person_id,
                $data['public_id'],
                $request->ip(),
            );
        } catch (InvalidArgumentException $e) {
            if (RequestPersonLinkByPublicId::isFormatError($e)) {
                throw new DomainException('Identifiant FANABE invalide.');
            }
            throw $e;
        }

        return response()->json(['message' => RequestPersonLinkByPublicId::UNIFORM_MESSAGE], 202);
    }
}
