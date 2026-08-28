<?php

namespace App\Http\Api\V1\School;

use App\Domain\Enrollment\Actions\ReadAcademicHistory;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class AcademicHistoryController extends Controller
{
    public function __invoke(string $school, string $person, ReadAcademicHistory $read): JsonResponse
    {
        $history = $read->forSchool(TenantContext::requireSchoolId(), $person);

        return response()->json([
            'data' => [
                'own' => $history['own'],
                'shared' => $history['shared'],
            ],
        ]);
    }
}
