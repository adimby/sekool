<?php

namespace App\Http\Api\V1\Auth;

use App\Domain\Identity\Models\UserAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof UserAccount) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json(SessionPayload::for($user, (string) $request->bearerToken()));
    }
}
