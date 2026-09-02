<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\Message;
use App\Domain\Communication\Models\MessageDelivery;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class MarkPrintHanded
{
    public function execute(string $schoolId, string $messageId): Message
    {
        $message = Message::query()->with('deliveries')->find($messageId);
        if ($message === null || (string) $message->school_id !== $schoolId || $message->channel !== 'print') {
            throw new DomainException('Courrier introuvable.', 404);
        }

        $latest = $message->deliveries->sortByDesc('occurred_at')->first();
        if ($latest !== null && $latest->status === 'printed') {
            return $message;
        }

        MessageDelivery::query()->create([
            'school_id' => $schoolId,
            'message_id' => $message->id,
            'status' => 'printed',
            'occurred_at' => now(),
        ]);

        Auditor::record('message.printed', 'message', $message->id, $message->subject_person_id, [
            'channel' => 'print',
        ]);

        return $message->fresh('deliveries');
    }
}
