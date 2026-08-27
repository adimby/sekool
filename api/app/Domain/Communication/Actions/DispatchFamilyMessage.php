<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\Message;
use App\Domain\Communication\Models\MessageDelivery;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Communication\Ports\SmsGateway;
use App\Domain\Communication\Support\MessageRenderer;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\Reliability\Support\ReliabilityIndexes;
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
            $this->emitTrust($schoolId, $subjectPersonId, $message, $channel, $status);

            return $message;
        }

        MessageDelivery::query()->create([
            'school_id' => $schoolId,
            'message_id' => $message->id,
            'status' => $status,
            'occurred_at' => now(),
        ]);
        $this->emitTrust($schoolId, $subjectPersonId, $message, $channel, $status);

        return $message;
    }

    private function emitTrust(
        string $schoolId,
        string $subjectPersonId,
        Message $message,
        string $channel,
        string $status,
    ): void {
        $familyId = FamilyMember::query()
            ->where('person_id', $subjectPersonId)
            ->whereNull('left_at')
            ->value('family_id');

        if (in_array($status, ['delivered', 'ready_to_print'], true)) {
            TrustEvent::emit(
                ReliabilityIndexes::SUBJECT_SCHOOL,
                $schoolId,
                'family_contacted',
                $schoolId,
                'message',
                (string) $message->id,
                ['channel' => $channel, 'status' => $status],
            );
        }

        if ($familyId === null) {
            return;
        }

        $instrumented = ($channel === 'in_app' && $status === 'delivered')
            || ($channel === 'sms' && $status === 'delivered');

        if ($instrumented) {
            TrustEvent::emit(
                ReliabilityIndexes::SUBJECT_RELATIONSHIP,
                (string) $familyId,
                'message_delivered',
                $schoolId,
                'message',
                (string) $message->id,
                ['channel' => $channel, 'status' => $status],
            );

            return;
        }

        if ($channel === 'print' || in_array($status, ['ready_to_print', 'unknown'], true)) {
            TrustEvent::emit(
                ReliabilityIndexes::SUBJECT_RELATIONSHIP,
                (string) $familyId,
                'message_uninstrumented',
                $schoolId,
                'message',
                (string) $message->id,
                [
                    'channel' => $channel,
                    'status' => $status === 'ready_to_print' ? 'unknown' : $status,
                ],
            );
        }
    }
}
