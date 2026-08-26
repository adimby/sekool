<?php

namespace App\Http\Api\V1\Auth;

use App\Domain\Identity\Models\UserAccount;
use App\Http\Controllers\Controller;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $account = TenantContext::runWithRlsBypass(
            fn (): ?UserAccount => UserAccount::query()->where('email', $data['email'])->first(),
        );

        if ($account === null || ! Hash::check($data['password'], $account->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        if ($account->locked_until !== null && $account->locked_until->isFuture()) {
            throw ValidationException::withMessages([
                'email' => ['Compte temporairement verrouillé.'],
            ]);
        }

        TenantContext::runWithRlsBypass(function () use ($account): void {
            $account->forceFill([
                'failed_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
            ])->save();
        });

        $token = $account->createToken('api')->plainTextToken;

        return response()->json(SessionPayload::for($account, $token));
    }
}
