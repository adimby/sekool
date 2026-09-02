<?php

namespace App\Http\Api\V1\School;

use App\Domain\Identity\Actions\FindSimilarPersons;
use App\Domain\Identity\Actions\RequestIdentityMerge;
use App\Domain\Identity\Models\IdentityMerge;
use App\Domain\Identity\Support\IdentityMergePayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IdentityMergeController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = IdentityMerge::query()
            ->with(['surviving', 'duplicate'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (IdentityMerge $row): array => IdentityMergePayload::row($row))->values(),
        ]);
    }

    public function duplicates(Request $request, string $school, FindSimilarPersons $find): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        return response()->json([
            'data' => $find->inSchool($school, $data),
        ]);
    }

    public function store(Request $request, string $school, RequestIdentityMerge $requestMerge): JsonResponse
    {
        $data = $request->validate([
            'surviving_public_id' => ['required', 'string', 'max:32'],
            'duplicate_public_id' => ['required', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $row = $requestMerge->execute(
            $school,
            (string) $request->user()->person_id,
            $data['surviving_public_id'],
            $data['duplicate_public_id'],
            $data['reason'],
        );

        return response()->json(['data' => IdentityMergePayload::row($row)], 201);
    }
}
