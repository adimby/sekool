<?php

namespace App\Domain\Communication\Support;

use App\Domain\Academic\Support\PersonMini;
use App\Domain\Communication\Models\Message;
use App\Domain\Identity\Models\Person;

final class PaperOutboxPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function letter(Message $message): array
    {
        $message->loadMissing('deliveries');
        $student = Person::query()->find($message->subject_person_id);

        return [
            'id' => $message->id,
            'channel' => $message->channel,
            'template_key' => $message->template_key,
            'subject' => $message->payload['subject'] ?? '',
            'body' => $message->payload['body'] ?? '',
            'queued_at' => $message->queued_at?->toIso8601String(),
            'delivery_status' => self::deliveryStatus($message),
            'student' => PersonMini::make($student),
        ];
    }

    public static function deliveryStatus(Message $message): string
    {
        $deliveries = $message->deliveries;
        if ($deliveries->contains(fn ($row): bool => $row->status === 'printed')) {
            return 'printed';
        }

        return $deliveries->sortByDesc('occurred_at')->first()?->status ?? 'queued';
    }
}
