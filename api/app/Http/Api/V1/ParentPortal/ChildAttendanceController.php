<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChildAttendanceController extends Controller
{
    public function __invoke(Request $request, string $person): JsonResponse
    {
        if (! ParentAuthorization::canSeeAttendance($request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $from = $request->query('from', now()->subDays(14)->toDateString());
        $to = $request->query('to', now()->toDateString());

        return TenantContext::runWithRlsBypass(function () use ($person, $from, $to): JsonResponse {
            $enrollmentIds = Enrollment::query()
                ->withoutGlobalScopes()
                ->where('person_id', $person)
                ->where('status', 'active')
                ->pluck('id');

            $rows = AttendanceRecord::query()
                ->withoutGlobalScopes()
                ->whereIn('enrollment_id', $enrollmentIds)
                ->whereBetween('date', [$from, $to])
                ->orderByDesc('date')
                ->get();

            return response()->json([
                'data' => $rows->map(fn (AttendanceRecord $row): array => [
                    'id' => $row->id,
                    'date' => $row->date?->toDateString(),
                    'session' => $row->session->value,
                    'status' => $row->status->value,
                ])->values(),
            ]);
        });
    }
}
