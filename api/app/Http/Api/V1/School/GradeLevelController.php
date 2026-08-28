<?php

namespace App\Http\Api\V1\School;

use App\Domain\Academic\Actions\ApplyGradePacks;
use App\Domain\Academic\Actions\CreateGradeLevel;
use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Support\GradePacks;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function applyPacks(Request $request, ApplyGradePacks $apply): JsonResponse
    {
        $data = $request->validate([
            'packs' => ['required', 'array', 'min:1'],
            'packs.*' => ['required', 'string', Rule::in(GradePacks::keys())],
        ]);

        $result = $apply->execute(
            (string) $request->route('school'),
            array_values(array_unique($data['packs'])),
        );

        return response()->json([
            'data' => [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'grades' => GradeLevel::query()->orderBy('sequence')->orderBy('name')->get(),
            ],
        ]);
    }
}
