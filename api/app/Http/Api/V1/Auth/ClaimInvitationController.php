<?php

namespace App\Http\Api\V1\Auth;

use App\Domain\Identity\Actions\ClaimParentInvitation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClaimInvitationController extends Controller
{
    public function __invoke(Request $request, ClaimParentInvitation $claim): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $result = $claim->execute($data['code'], $data['email'], $data['password']);

        return response()->json(SessionPayload::for($result['account'], $result['token']));
    }
}
