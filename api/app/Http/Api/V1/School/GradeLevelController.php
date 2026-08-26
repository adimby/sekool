<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\CreateGradeLevel;
use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\GradeLevel;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GradeLevelController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => GradeLevel::query()->orderBy('sequence')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CreateGradeLevel $create): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'stage' => ['required', 'string'],
            'sequence' => ['required', 'integer', 'min:0'],
        ]);

        $stage = GradeStage::tryFrom($data['stage']);
        if ($stage === null) {
            return response()->json(['message' => 'Niveau (stage) inconnu.'], 422);
        }

        $grade = $create->execute(
            schoolId: (string) $request->route('school'),
            name: $data['name'],
            stage: $stage,
            sequence: (int) $data['sequence'],
        );

        return response()->json(['data' => $grade], 201);
    }
}
