<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\Message;
use App\Domain\Communication\Models\MessageDelivery;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Communication\Ports\SmsGateway;
use App\Domain\Communication\Support\MessageRenderer;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Database\UniqueConstraintViolationException;

final class DispatchFamilyMessage
{
    public function __construct(private readonly SmsGateway $sms) {}

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function execute(
        string $schoolId,
        string $templateKey,
        string $channel,
        string $subjectPersonId,
        string $recipientPersonId,
        array $variables,
        string $idempotencyKey,
        bool $deliverNow,
        ?string $workflowRunId = null,
        string $priority = 'normal',
    ): ?Message {
        if ($channel === 'sms' && ! (bool) config('fanabe.sms_enabled', false)) {
            return null;
        }

        $existing = Message::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $template = MessageTemplate::query()
            ->where('key', $templateKey)
            ->where('channel', $channel)
            ->where('locale', 'fr')
            ->first();

        if ($template === null) {
            throw new DomainException("Gabarit {$templateKey}/{$channel} introuvable.");
        }

        $subject = MessageRenderer::render($template->subject, $variables);
        $body = MessageRenderer::render($template->body, $variables);
        MessageRenderer::assertFamilySafe($subject, $body);

        try {
            $message = Message::query()->create([
                'school_id' => $schoolId,
                'template_key' => $templateKey,
                'subject_person_id' => $subjectPersonId,
                'recipient_person_id' => $recipientPersonId,
                'channel' => $channel,
                'payload' => [
                    'subject' => $subject,
                    'body' => $body,
                    'variables' => $variables,
                ],
                'queued_at' => now(),
                'sent_at' => $deliverNow ? now() : null,
                'priority' => $priority,
                'workflow_run_id' => $workflowRunId,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Message::query()->where('idempotency_key', $idempotencyKey)->first();
        }

        $status = $deliverNow
            ? ($channel === 'print' ? 'ready_to_print' : 'delivered')
            : 'queued';

        if ($channel === 'sms' && $deliverNow) {
            $result = $this->sms->send((string) ($variables['phone_e164'] ?? ''), $body);
            $status = $result['sent'] ? 'delivered' : 'failed';
            if (! $result['sent']) {
                $message->forceFill(['sent_at' => null])->save();
            }
            MessageDelivery::query()->create([
                'school_id' => $schoolId,
                'message_id' => $message->id,
                'status' => $status,
                'occurred_at' => now(),
                'provider_reference' => $result['provider_reference'],
                'error_code' => $result['error'],
            ]);

            return $message;
        }

        MessageDelivery::query()->create([
            'school_id' => $schoolId,
            'message_id' => $message->id,
            'status' => $status,
            'occurred_at' => now(),
        ]);

        return $message;
    }
}
