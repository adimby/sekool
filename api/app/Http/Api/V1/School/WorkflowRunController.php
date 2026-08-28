<?php

namespace App\Http\Api\V1\School;

use App\Domain\Workflow\Actions\EvaluateWorkflows;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkflowRunController extends Controller
{
    public function __invoke(Request $request, string $school, EvaluateWorkflows $evaluate): JsonResponse
    {
        $data = $request->validate([
            'live' => ['nullable', 'boolean'],
        ]);

        $runs = $evaluate->execute($school, forceLive: (bool) ($data['live'] ?? false));

        return response()->json([
            'data' => collect($runs)->map(fn ($run): array => [
                'id' => $run->id,
                'status' => $run->status,
                'subject_id' => $run->subject_id,
                'idempotency_key' => $run->idempotency_key,
            ])->all(),
        ]);
    }
}
