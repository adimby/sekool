<?php

namespace App\Http\Api\V1\School;

use App\Domain\Collection\Support\CollectionPayload;
use App\Domain\Communication\Models\Message;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class OutboxController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $messages = Message::query()->orderByDesc('queued_at')->limit(100)->get();

        return response()->json([
            'data' => $messages->map(fn (Message $message): array => CollectionPayload::message($message, staff: true))->all(),
        ]);
    }
}
