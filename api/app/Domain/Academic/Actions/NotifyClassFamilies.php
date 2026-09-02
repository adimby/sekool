<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Collection\Support\FamilyRecipients;
use App\Domain\Communication\Actions\DispatchFamilyMessage;
use App\Domain\Communication\Support\EnsureMessageTemplates;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\Person;
use App\Domain\School\Models\School;
use Illuminate\Support\Collection;

final class NotifyClassFamilies
{
    public function __construct(private readonly DispatchFamilyMessage $messages) {}

    /**
     * @param  Collection<int, Enrollment>|list<Enrollment>  $enrollments
     * @param  list<string>  $channels
     * @param  array<string, scalar|null>  $variables
     */
    public function execute(
        string $schoolId,
        string $templateKey,
        iterable $enrollments,
        array $channels,
        array $variables,
        string $sourceId,
    ): void {
        EnsureMessageTemplates::forKeys($schoolId, $templateKey);

        $schoolName = $variables['school_name']
            ?? School::query()->find($schoolId)?->name
            ?? 'l’école';

        foreach ($enrollments as $enrollment) {
            $enrollment->loadMissing('person');
            $student = $enrollment->person;
            if (! $student instanceof Person) {
                continue;
            }

            $rowVariables = [
                ...$variables,
                'student_first_name' => $student->first_name,
                'student_last_name' => $student->last_name,
                'school_name' => $schoolName,
            ];

            foreach (FamilyRecipients::adultsForStudent((string) $student->id) as $adult) {
                foreach ($channels as $channel) {
                    $this->messages->execute(
                        schoolId: $schoolId,
                        templateKey: $templateKey,
                        channel: $channel,
                        subjectPersonId: (string) $student->id,
                        recipientPersonId: (string) $adult->id,
                        variables: $rowVariables,
                        idempotencyKey: $templateKey.':'.$channel.':'.$sourceId.':'.$student->id.':'.$adult->id,
                        deliverNow: true,
                        priority: 'normal',
                    );
                }
            }
        }
    }
}
