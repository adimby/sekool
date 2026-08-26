<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Collection\Support\CollectionPayload;
use App\Domain\Communication\Models\Message;
use App\Domain\Communication\Support\MessageRenderer;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ParentMessageController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $parentId = (string) $request->user()->person_id;
        $childIds = ParentAuthorization::authorizedChildIds($parentId);

        $messages = TenantContext::runWithRlsBypass(fn () => Message::query()
            ->withoutGlobalScopes()
            ->where('recipient_person_id', $parentId)
            ->where('channel', 'in_app')
            ->where(function ($query) use ($childIds): void {
                $query->whereIn('subject_person_id', $childIds);
            })
            ->orderByDesc('queued_at')
            ->limit(50)
            ->get());

        $data = $messages->map(function (Message $message): array {
            $payload = CollectionPayload::message($message);
            MessageRenderer::assertFamilySafe($payload['subject'], $payload['body']);

            return $payload;
        })->all();

        return response()->json(['data' => $data]);
    }
}
