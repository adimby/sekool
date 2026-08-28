<?php

namespace App\Http\Api\V1\School;

use App\Domain\Certificate\Actions\AttestExternalDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocumentAttestController extends Controller
{
    public function __invoke(Request $request, string $school, string $document, AttestExternalDocument $attest): JsonResponse
    {
        $row = $attest->execute($school, $document, (string) $request->user()->person_id);

        return response()->json([
            'data' => [
                'id' => $row->id,
                'source_type' => $row->source_type,
                'verification_status' => $row->verification_status,
            ],
        ]);
    }
}
