<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Audit\AuditEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccessLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $parentId = $request->user()->person_id;
        $subjectIds = array_merge([$parentId], ParentAuthorization::authorizedChildIds($parentId));

        $rows = AuditEvent::query()
            ->whereIn('subject_person_id', $subjectIds)
            ->orderByDesc('occurred_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows->map(fn (AuditEvent $row): array => [
            'id' => $row->id,
            'occurred_at' => $row->occurred_at?->toIso8601String(),
            'action' => $row->action,
            'resource_type' => $row->resource_type,
            'outcome' => $row->outcome,
        ])->values()]);
    }
}
