<?php

namespace App\Http\Api\V1\Platform;

use App\Domain\Identity\Actions\DecideIdentityMerge;
use App\Domain\Identity\Models\IdentityMerge;
use App\Domain\Identity\Support\IdentityMergePayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformIdentityMergeController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = IdentityMerge::query()
            ->withoutGlobalScopes()
            ->with(['surviving', 'duplicate'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (IdentityMerge $row): array => IdentityMergePayload::row($row))->values(),
        ]);
    }

    public function approve(Request $request, string $merge, DecideIdentityMerge $decide): JsonResponse
    {
        $row = $decide->approve($merge, (string) $request->user()->person_id);

        return response()->json(['data' => IdentityMergePayload::row($row)]);
    }

    public function refuse(Request $request, string $merge, DecideIdentityMerge $decide): JsonResponse
    {
        $row = $decide->refuse($merge, (string) $request->user()->person_id);

        return response()->json(['data' => IdentityMergePayload::row($row)]);
    }

    public function undo(Request $request, string $merge, DecideIdentityMerge $decide): JsonResponse
    {
        $row = $decide->undo($merge, (string) $request->user()->person_id);

        return response()->json(['data' => IdentityMergePayload::row($row)]);
    }
}
