<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\ExamSession;
use App\Domain\Communication\Support\MessageRenderer;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\School;
use Carbon\Carbon;

final class RecordExamSession
{
    public function __construct(private readonly NotifyClassFamilies $notify) {}

    /**
     * @param  array{
     *     title: string,
     *     subject?: string|null,
     *     held_on: string,
     *     starts_at: string,
     *     ends_at: string,
     *     room?: string|null,
     *     body?: string|null
     * }  $data
     */
    public function execute(string $schoolId, string $classroomId, string $actorPersonId, array $data): ExamSession
    {
        $classroom = Classroom::query()->find($classroomId);
        if ($classroom === null || (string) $classroom->school_id !== $schoolId) {
            throw new DomainException('Classe introuvable.', 404);
        }

        $title = trim($data['title']);
        if ($title === '') {
            throw new DomainException('Indiquez le titre de la composition.');
        }
        MessageRenderer::assertFamilySafe($title);

        $starts = $this->normalizeTime($data['starts_at']);
        $ends = $this->normalizeTime($data['ends_at']);
        if ($ends <= $starts) {
            throw new DomainException('L’heure de fin doit être après l’heure de début.');
        }

        $body = isset($data['body']) && trim((string) $data['body']) !== '' ? trim((string) $data['body']) : null;
        if ($body !== null) {
            MessageRenderer::assertFamilySafe($body);
        }

        $room = isset($data['room']) && trim((string) $data['room']) !== '' ? trim((string) $data['room']) : null;
        $subject = isset($data['subject']) && trim((string) $data['subject']) !== '' ? trim((string) $data['subject']) : null;

        $heldOn = Carbon::parse($data['held_on'])->startOfDay();
        if ($heldOn->lt(now()->startOfDay())) {
            throw new DomainException('La composition doit être aujourd’hui ou plus tard.');
        }

        $this->assertNoExamOverlap($classroomId, $data['held_on'], $starts, $ends);
        $this->assertNoRoomOverlap($room, $data['held_on'], $starts, $ends);

        $exam = ExamSession::query()->create([
            'school_id' => $schoolId,
            'classroom_id' => $classroomId,
            'title' => $title,
            'subject' => $subject,
            'held_on' => $data['held_on'],
            'starts_at' => $starts,
            'ends_at' => $ends,
            'room' => $room,
            'body' => $body,
            'created_by_person_id' => $actorPersonId,
        ]);

        $enrollments = Enrollment::query()
            ->where('classroom_id', $classroomId)
            ->where('status', EnrollmentStatus::Active)
            ->get();

        $this->notify->execute(
            schoolId: $schoolId,
            templateKey: 'exam_session',
            enrollments: $enrollments,
            channels: ['in_app', 'print'],
            variables: [
                'title' => $title,
                'date' => Carbon::parse($data['held_on'])->format('d/m/Y'),
                'school_name' => School::query()->find($schoolId)?->name ?? 'l’école',
            ],
            sourceId: (string) $exam->id,
        );

        Auditor::record('exam.scheduled', 'exam_session', $exam->id, null, [
            'classroom_id' => $classroomId,
        ]);

        return $exam->load('classroom');
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value.':00';
        }

        return $value;
    }

    private function assertNoExamOverlap(string $classroomId, string $heldOn, string $starts, string $ends): void
    {
        $exists = ExamSession::query()
            ->where('classroom_id', $classroomId)
            ->whereDate('held_on', $heldOn)
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts)
            ->exists();

        if ($exists) {
            throw new DomainException('Cette classe a déjà une composition à cette heure.');
        }
    }

    private function assertNoRoomOverlap(?string $room, string $heldOn, string $starts, string $ends): void
    {
        if ($room === null) {
            return;
        }

        $exists = ExamSession::query()
            ->where('room', $room)
            ->whereDate('held_on', $heldOn)
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts)
            ->exists();

        if ($exists) {
            throw new DomainException('Cette salle est déjà prise pour une composition à cette heure.');
        }
    }
}
