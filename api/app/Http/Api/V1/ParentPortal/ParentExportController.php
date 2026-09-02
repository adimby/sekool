<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Identity\Actions\ExportFamilyArchive;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ParentExportController extends Controller
{
    public function __invoke(Request $request, ExportFamilyArchive $export): JsonResponse
    {
        $archive = $export->execute((string) $request->user()->person_id);

        return response()->json($archive);
    }
}
