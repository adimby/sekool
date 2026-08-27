<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Identity\Actions\ResolvePersonLinkRequest;
use App\Domain\Identity\Models\PersonLinkRequest;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\School;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LinkRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = TenantContext::runWithRlsBypass(fn () => PersonLinkRequest::query()
            ->withoutGlobalScopes()
            ->where('matched_person_id', $request->user()->person_id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->get());

        $schools = School::query()->whereIn('id', $rows->pluck('school_id'))->get()->keyBy('id');

        return response()->json(['data' => $rows->map(fn (PersonLinkRequest $row) => [
            'id' => $row->id,
            'school_id' => $row->school_id,
            'school_name' => $schools->get($row->school_id)?->name,
            'status' => $row->status,
            'expires_at' => $row->expires_at,
        ])->values()]);
    }

    public function approve(Request $request, string $linkRequest, ResolvePersonLinkRequest $resolve): JsonResponse
    {
        $row = $resolve->approve($linkRequest, $request->user()->person_id);

        return response()->json(['data' => ['id' => $row->id, 'status' => $row->status]]);
    }

    public function refuse(Request $request, string $linkRequest, ResolvePersonLinkRequest $resolve): JsonResponse
    {
        $row = $resolve->refuse($linkRequest, $request->user()->person_id);

        return response()->json(['data' => ['id' => $row->id, 'status' => $row->status]]);
    }
}
