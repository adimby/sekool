<?php

namespace App\Http\Controllers;

use App\Domain\Certificate\Actions\VerifyCertificateToken;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class VerifyCertificatePageController extends Controller
{
    public function __invoke(Request $request, string $token, VerifyCertificateToken $verify): View
    {
        $birthDate = $request->query('birth_date');
        $birthDate = is_string($birthDate) ? $birthDate : null;

        return view('verify.certificate', [
            'token' => $token,
            'result' => $verify->execute($token, $birthDate),
            'birthDate' => $birthDate ?? '',
        ]);
    }
}
