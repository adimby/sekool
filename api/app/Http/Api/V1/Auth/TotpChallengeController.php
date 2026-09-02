<?php

namespace App\Http\Api\V1\Auth;

use App\Domain\Identity\Actions\CompleteTotpChallenge;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TotpChallengeController extends Controller
{
    public function __invoke(Request $request, CompleteTotpChallenge $complete): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $account = $complete->execute($data['challenge_id'], $data['code']);
        $token = $account->createToken('api')->plainTextToken;

        return response()->json(SessionPayload::for($account, $token));
    }
}
