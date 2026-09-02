<?php

namespace App\Http\Api\V1\Auth;

use App\Domain\Identity\Support\PrivilegedAccount;
use App\Domain\Identity\Support\SensitiveReauth;
use App\Domain\Identity\Support\Totp;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ReauthController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $account = $request->user();
        $totp = PrivilegedAccount::requiresTotp($account);
        $payload = ['method' => $totp ? 'totp' : 'password'];

        if ($totp && app()->environment(['local', 'testing']) && $account->totp_secret_encrypted !== null) {
            $payload['demo_code'] = Totp::code(decrypt($account->totp_secret_encrypted));
        }

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $account = $request->user();
        $data = $request->validate([
            'password' => ['nullable', 'string'],
            'code' => ['nullable', 'string', 'max:12'],
        ]);

        if (PrivilegedAccount::requiresTotp($account)) {
            $secret = $account->totp_secret_encrypted !== null ? decrypt($account->totp_secret_encrypted) : null;
            if ($secret === null || ! Totp::verify($secret, (string) ($data['code'] ?? ''))) {
                throw ValidationException::withMessages([
                    'code' => ['Code TOTP invalide.'],
                ]);
            }
        } else {
            $hash = $account->getRawOriginal('password');
            $ok = is_string($hash) && $hash !== '' && (Hash::check((string) ($data['password'] ?? ''), $hash) || password_verify((string) ($data['password'] ?? ''), $hash));
            if (! $ok) {
                throw ValidationException::withMessages([
                    'password' => ['Identifiants invalides.'],
                ]);
            }
        }

        SensitiveReauth::grant($account);

        return response()->json(['ok' => true, 'expires_in' => SensitiveReauth::TTL_SECONDS]);
    }
}
