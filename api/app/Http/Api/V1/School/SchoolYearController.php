<?php

namespace App\Http\Api\V1\School;

use App\Domain\School\Models\SchoolYear;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SchoolYearController extends Controller
{
    public function index(): JsonResponse
    {
        $years = SchoolYear::query()->orderBy('starts_on', 'desc')->get();

        return response()->json(['data' => $years]);
    }

    public function show(string $school, string $year): JsonResponse
    {
        $model = SchoolYear::query()->find($year);

        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => $model]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:32'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $year = SchoolYear::query()->create($data);

        return response()->json(['data' => $year], 201);
    }
}
