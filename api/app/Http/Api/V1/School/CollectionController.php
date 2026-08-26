<?php

namespace App\Http\Api\V1\School;

use App\Domain\Collection\Actions\CloseCollectionTask;
use App\Domain\Collection\Actions\TakeCollectionAction;
use App\Domain\Collection\Models\CollectionForecast;
use App\Domain\Collection\Models\CollectionTask;
use App\Domain\Collection\Support\CollectionPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CollectionController extends Controller
{
    public function queue(): JsonResponse
    {
        $tasks = CollectionTask::query()
            ->with('enrollment.person')
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByRaw("case priority when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderBy('created_at')
            ->get();

        $forecast = CollectionForecast::query()->orderByDesc('week_starting_on')->first();

        return response()->json([
            'forecast' => CollectionPayload::forecast($forecast),
            'data' => $tasks->map(fn (CollectionTask $task): array => CollectionPayload::task($task))->all(),
        ]);
    }

    public function relance(Request $request, string $school, string $task, TakeCollectionAction $action): JsonResponse
    {
        $taskModel = $action->execute($school, $task, $request->user()->person_id);

        return response()->json(['data' => CollectionPayload::task($taskModel)]);
    }

    public function resolve(Request $request, string $school, string $task, CloseCollectionTask $close): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $taskModel = $close->execute($school, $task, 'resolved', $data['notes'] ?? null);

        return response()->json(['data' => CollectionPayload::task($taskModel)]);
    }

    public function dismiss(Request $request, string $school, string $task, CloseCollectionTask $close): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $taskModel = $close->execute($school, $task, 'dismissed', $data['notes'] ?? 'Écartée');

        return response()->json(['data' => CollectionPayload::task($taskModel)]);
    }
}
