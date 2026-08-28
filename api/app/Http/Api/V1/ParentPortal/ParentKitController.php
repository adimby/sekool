<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\SchoolKit\Actions\PlaceKitOrder;
use App\Domain\SchoolKit\Models\KitDefinition;
use App\Domain\SchoolKit\Models\KitOrder;
use App\Domain\SchoolKit\Support\KitPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ParentKitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $parentId = (string) $request->user()->person_id;
        $childIds = ParentAuthorization::accessibleChildIds($parentId);

        return TenantContext::runWithRlsBypass(function () use ($childIds): JsonResponse {
            $enrollments = Enrollment::query()
                ->withoutGlobalScopes()
                ->with(['person', 'classroom.gradeLevel', 'school'])
                ->whereIn('person_id', $childIds)
                ->where('status', EnrollmentStatus::Active)
                ->get();

            $gradeIds = $enrollments->pluck('classroom.grade_level_id')->filter()->unique()->values();
            $yearIds = $enrollments->pluck('school_year_id')->unique()->values();

            $definitions = KitDefinition::query()
                ->withoutGlobalScopes()
                ->with(['needs', 'packs.supplier', 'packs.items', 'gradeLevel'])
                ->whereIn('school_id', $enrollments->pluck('school_id'))
                ->whereIn('school_year_id', $yearIds)
                ->where(function ($query) use ($gradeIds): void {
                    $query->whereNull('grade_level_id')->orWhereIn('grade_level_id', $gradeIds);
                })
                ->get();

            $orders = KitOrder::query()
                ->withoutGlobalScopes()
                ->with(['pack.supplier', 'enrollment.person', 'supplier', 'definition'])
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->orderByDesc('placed_at')
                ->get();

            return response()->json([
                'children' => $enrollments->map(fn (Enrollment $row): array => [
                    'person_id' => $row->person_id,
                    'enrollment_id' => $row->id,
                    'first_name' => $row->person?->first_name,
                    'last_name' => $row->person?->last_name,
                    'school' => $row->school?->name,
                    'classroom' => $row->classroom?->name,
                    'grade_level_id' => $row->classroom?->grade_level_id,
                ])->values(),
                'catalog' => $definitions->map(fn (KitDefinition $row): array => KitPayload::definition($row))->values(),
                'orders' => $orders->map(fn (KitOrder $row): array => KitPayload::order($row))->values(),
            ]);
        });
    }

    public function store(Request $request, PlaceKitOrder $place): JsonResponse
    {
        $data = $request->validate([
            'enrollment_id' => ['required', 'uuid'],
            'fulfillment' => ['nullable', 'string', 'in:partner,self'],
            'kit_pack_id' => ['nullable', 'uuid'],
            'kit_definition_id' => ['nullable', 'uuid'],
        ]);

        $parentId = (string) $request->user()->person_id;

        $enrollment = TenantContext::runWithRlsBypass(
            fn () => Enrollment::query()->withoutGlobalScopes()->find($data['enrollment_id']),
        );

        if ($enrollment === null || ! ParentAuthorization::canSeeFinance($parentId, (string) $enrollment->person_id)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $order = $place->execute([
            'enrollment_id' => $data['enrollment_id'],
            'actor_person_id' => $parentId,
            'fulfillment' => $data['fulfillment'] ?? 'partner',
            'kit_pack_id' => $data['kit_pack_id'] ?? null,
            'kit_definition_id' => $data['kit_definition_id'] ?? null,
        ]);

        return response()->json(['data' => KitPayload::order($order)], 201);
    }
}
