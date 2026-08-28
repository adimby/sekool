<?php

namespace App\Http\Api\V1\School;

use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Support\SchoolGate;
use App\Domain\SchoolKit\Actions\CopyKitListsFromYear;
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
    public function index(Request $request): JsonResponse
    {
        $query = KitDefinition::query()
            ->with(['needs', 'packs.supplier', 'packs.items', 'gradeLevel'])
            ->orderBy('name');

        $gradeIds = SchoolGate::visibleKitGradeIds($request);
        if ($gradeIds !== null) {
            $query->whereIn('grade_level_id', $gradeIds);
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows->map(fn (KitDefinition $row): array => KitPayload::definition($row))->values(),
        ]);
    }

    public function store(Request $request, string $school, SaveKitCatalog $save): JsonResponse
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'grade_level_id' => ['required', 'uuid'],
            'name' => ['nullable', 'string', 'max:160'],
            'price_source' => ['nullable', 'string', 'in:supplier,purchasing'],
            'needs' => ['nullable', 'array'],
            'needs.*.label' => ['required_with:needs', 'string', 'max:160'],
            'needs.*.quantity' => ['nullable', 'integer', 'min:1'],
            'needs.*.notes' => ['nullable', 'string', 'max:500'],
            'needs.*.offers' => ['nullable', 'array'],
            'needs.*.offers.*.tier' => ['required_with:needs.*.offers', 'string'],
            'needs.*.offers.*.brand' => ['nullable', 'string', 'max:120'],
            'needs.*.offers.*.unit_amount' => ['nullable', 'integer', 'min:0'],
            'needs.*.offers.*.quantity' => ['nullable', 'integer', 'min:1'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'supplier_contact' => ['nullable', 'string', 'max:160'],
            'commission_rate_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'packs' => ['nullable', 'array'],
            'packs.*.tier' => ['required_with:packs', 'string'],
            'packs.*.total_amount' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! SchoolGate::canEditKit($request, $data['grade_level_id'])) {
            return response()->json(['message' => 'Cette liste n’appartient pas à votre classe.'], 403);
        }

        $definition = $save->execute($school, $data);
        $created = $definition->wasRecentlyCreated;

        return response()->json(['data' => KitPayload::definition($definition)], $created ? 201 : 200);
    }

    public function copyYear(Request $request, string $school, CopyKitListsFromYear $copy): JsonResponse
    {
        $data = $request->validate([
            'from_year_id' => ['required', 'uuid'],
            'to_year_id' => ['required', 'uuid'],
        ]);

        try {
            $rows = $copy->execute($school, $data['from_year_id'], $data['to_year_id']);
        } catch (DomainException $e) {
            throw $e;
        }

        return response()->json([
            'data' => collect($rows)->map(fn (KitDefinition $row): array => KitPayload::definition($row))->values(),
        ], 201);
    }

    public function orders(): JsonResponse
    {
        $rows = KitOrder::query()
            ->with(['pack.supplier', 'enrollment.person', 'supplier', 'definition'])
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
        if ($status === null || $status === KitOrderStatus::Draft || $status === KitOrderStatus::SelfSupplied) {
            return response()->json(['message' => 'Statut de commande inconnu.'], 422);
        }

        $row = $update->execute($school, $order, $status);

        return response()->json(['data' => KitPayload::order($row)]);
    }
}
