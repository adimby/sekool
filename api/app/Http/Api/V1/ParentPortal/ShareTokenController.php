<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Identity\Actions\GenerateFamilyShareToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShareTokenController extends Controller
{
    public function store(Request $request, GenerateFamilyShareToken $generate): JsonResponse
    {
        $data = $request->validate([
            'child_person_ids' => ['required', 'array', 'min:1'],
            'child_person_ids.*' => ['uuid'],
            'scopes' => ['sometimes', 'array'],
            'target_school_id' => ['nullable', 'uuid'],
        ]);

        $result = $generate->execute(
            $request->user()->person_id,
            $data['child_person_ids'],
            $data['scopes'] ?? ['identity.core', 'identity.contact'],
            $data['target_school_id'] ?? null,
        );

        return response()->json([
            'token' => $result['token'],
            'expires_at' => $result['share']->expires_at,
        ], 201);
    }
}
