<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Collection\Support\FamilyRecipients;
use App\Domain\Communication\Actions\DispatchFamilyMessage;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Communication\Support\MessageCatalog;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\School\Models\School;
use Carbon\Carbon;

final class NotifyAbsenceToFamily
{
    public const TEMPLATE = 'same_day_absence';

    public function __construct(private readonly DispatchFamilyMessage $messages) {}

    public function execute(Enrollment $enrollment, string $date): void
    {
        $enrollment->loadMissing(['person', 'school']);
        $student = $enrollment->person;
        if ($student === null) {
            return;
        }

        $this->ensureTemplates((string) $enrollment->school_id);

        $schoolName = $enrollment->school?->name
            ?? School::query()->find($enrollment->school_id)?->name
            ?? 'l’école';

        $formatted = Carbon::parse($date)->format('d/m/Y');
        $variables = [
            'student_first_name' => $student->first_name,
            'student_last_name' => $student->last_name,
            'school_name' => $schoolName,
            'date' => $formatted,
        ];

        foreach (FamilyRecipients::adultsForStudent((string) $student->id) as $adult) {
            foreach (['in_app', 'print'] as $channel) {
                $this->messages->execute(
                    schoolId: (string) $enrollment->school_id,
                    templateKey: self::TEMPLATE,
                    channel: $channel,
                    subjectPersonId: (string) $student->id,
                    recipientPersonId: (string) $adult->id,
                    variables: $variables,
                    idempotencyKey: self::TEMPLATE.':'.$channel.':'.$enrollment->id.':'.$date.':'.$adult->id,
                    deliverNow: true,
                    priority: 'high',
                );
            }
        }
    }

    private function ensureTemplates(string $schoolId): void
    {
        foreach (MessageCatalog::defaults() as $template) {
            if ($template['key'] !== self::TEMPLATE) {
                continue;
            }
            MessageTemplate::query()->firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'key' => $template['key'],
                    'channel' => $template['channel'],
                    'locale' => $template['locale'],
                ],
                [
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'version' => 1,
                ],
            );
        }
    }
}
