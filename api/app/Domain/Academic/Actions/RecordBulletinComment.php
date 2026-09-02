<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\BulletinComment;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Support\MessageRenderer;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class RecordBulletinComment
{
    /**
     * @param  array{
     *     body: string,
     *     subject_id?: string|null,
     *     academic_term_id?: string|null
     * }  $input
     */
    public function execute(string $schoolId, string $enrollmentId, string $actorPersonId, array $input): BulletinComment
    {
        $enrollment = Enrollment::query()->find($enrollmentId);
        if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
            throw new DomainException('Inscription introuvable.', 404);
        }
        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw new DomainException('Une appréciation ne s’enregistre que pour une inscription active.');
        }

        $body = trim($input['body']);
        if ($body === '') {
            throw new DomainException('Indiquez une appréciation.');
        }
        MessageRenderer::assertFamilySafe($body);

        $subjectId = isset($input['subject_id']) && is_string($input['subject_id']) && $input['subject_id'] !== ''
            ? $input['subject_id']
            : null;
        if ($subjectId !== null) {
            $subject = Subject::query()->find($subjectId);
            if ($subject === null || (string) $subject->school_id !== $schoolId) {
                throw new DomainException('Matière introuvable.', 404);
            }
        }

        $query = BulletinComment::query()
            ->where('enrollment_id', $enrollment->id);
        if ($subjectId === null) {
            $query->whereNull('subject_id');
        } else {
            $query->where('subject_id', $subjectId);
        }

        $comment = $query->first();
        $attributes = [
            'school_id' => $schoolId,
            'enrollment_id' => $enrollment->id,
            'academic_term_id' => $input['academic_term_id'] ?? null,
            'subject_id' => $subjectId,
            'body' => $body,
            'recorded_by_person_id' => $actorPersonId,
        ];

        if ($comment === null) {
            $comment = BulletinComment::query()->create($attributes);
        } else {
            $comment->fill($attributes);
            $comment->save();
        }

        Auditor::record('bulletin.commented', 'bulletin_comment', $comment->id, (string) $enrollment->person_id);

        return $comment->fresh();
    }
}
