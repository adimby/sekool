<?php

namespace App\Http\Api\V1\School;

use App\Domain\SchoolKit\Actions\SaveKitCatalog;
use App\Domain\SchoolKit\Actions\UpdateKitOrderStatus;
use App\Domain\SchoolKit\Enums\KitOrderStatus;
use App\Domain\SchoolKit\Models\KitDefinition;
use App\Domain\SchoolKit\Models\KitOrder;
use App\Domain\SchoolKit\Support\KitPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SchoolKitController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = KitDefinition::query()
            ->with(['needs', 'packs.supplier', 'gradeLevel'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (KitDefinition $row): array => KitPayload::definition($row))->values(),
        ]);
    }

    public function store(Request $request, string $school, SaveKitCatalog $save): JsonResponse
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'grade_level_id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:160'],
            'needs' => ['nullable', 'array'],
            'needs.*.label' => ['required_with:needs', 'string', 'max:160'],
            'needs.*.quantity' => ['nullable', 'integer', 'min:1'],
            'supplier_name' => ['required', 'string', 'max:160'],
            'supplier_contact' => ['nullable', 'string', 'max:160'],
            'commission_rate_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'packs' => ['required', 'array', 'min:1'],
            'packs.*.tier' => ['required', 'string'],
            'packs.*.total_amount' => ['required', 'integer', 'min:1'],
        ]);

        $definition = $save->execute($school, $data);

        return response()->json(['data' => KitPayload::definition($definition)], 201);
    }

    public function orders(): JsonResponse
    {
        $rows = KitOrder::query()
            ->with(['pack.supplier', 'enrollment.person', 'supplier'])
            ->orderByDesc('placed_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (KitOrder $row): array => KitPayload::order($row))->values(),
        ]);
    }

    public function updateOrder(Request $request, string $school, string $order, UpdateKitOrderStatus $update): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $status = KitOrderStatus::tryFrom($data['status']);
        if ($status === null || $status === KitOrderStatus::Draft) {
            return response()->json(['message' => 'Statut de commande inconnu.'], 422);
        }

        $row = $update->execute($school, $order, $status);

        return response()->json(['data' => KitPayload::order($row)]);
    }
}
