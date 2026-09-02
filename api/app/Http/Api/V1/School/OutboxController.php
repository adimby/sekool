<?php

namespace App\Http\Api\V1\School;

use App\Domain\Communication\Actions\MarkPrintHanded;
use App\Domain\Communication\Models\Message;
use App\Domain\Communication\Support\PaperOutboxPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class OutboxController extends Controller
{
    public function index(): JsonResponse
    {
        $messages = Message::query()
            ->with('deliveries')
            ->where('channel', 'print')
            ->whereHas('deliveries', fn ($query) => $query->where('status', 'ready_to_print'))
            ->whereDoesntHave('deliveries', fn ($query) => $query->where('status', 'printed'))
            ->orderByDesc('queued_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $messages->map(fn (Message $message): array => PaperOutboxPayload::letter($message))->values(),
        ]);
    }

    public function markPrinted(string $school, string $message, MarkPrintHanded $mark): JsonResponse
    {
        $row = $mark->execute($school, $message);

        return response()->json(['data' => PaperOutboxPayload::letter($row)]);
    }
}
