<?php

namespace App\Http\Api\V1\School;

use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolNetwork;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class SchoolNetworkController extends Controller
{
    public function show(string $school): JsonResponse
    {
        $model = School::query()->find($school);
        if ($model === null || $model->network_id === null) {
            return response()->json(['data' => null]);
        }

        $network = SchoolNetwork::query()->find($model->network_id);
        if ($network === null) {
            return response()->json(['data' => null]);
        }

        $campuses = School::query()
            ->where('network_id', $network->id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'city']);

        return response()->json([
            'data' => [
                'id' => $network->id,
                'name' => $network->name,
                'campuses' => $campuses
                    ->map(fn (School $campus): array => [
                        'id' => $campus->id,
                        'name' => $campus->name,
                        'code' => $campus->code,
                        'city' => $campus->city,
                    ])
                    ->values(),
            ],
        ]);
    }
}
