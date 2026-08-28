<?php

namespace App\Http\Api\V1\PublicVerify;

use App\Domain\Certificate\Actions\VerifyCertificateToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicCertificateVerifyController extends Controller
{
    public function __invoke(Request $request, string $token, VerifyCertificateToken $verify): JsonResponse
    {
        $birthDate = $request->query('birth_date');
        $birthDate = is_string($birthDate) ? $birthDate : null;

        return response()->json($verify->execute($token, $birthDate));
    }
}
