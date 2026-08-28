<?php

namespace App\Domain\Workflow\Actions;

use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Collection\Enums\RiskLevel;
use App\Domain\Collection\Models\CollectionTask;
use App\Domain\Collection\Models\RiskAssessment;
use App\Domain\Collection\Support\EnrollmentInstallments;
use App\Domain\Collection\Support\FamilyRecipients;
use App\Domain\Collection\Support\QuietHours;
use App\Domain\Communication\Actions\DispatchFamilyMessage;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\FanabeDocument;
use App\Domain\School\Models\School;
use App\Domain\Workflow\Models\WorkflowAction;
use App\Domain\Workflow\Models\WorkflowRule;
use App\Domain\Workflow\Models\WorkflowRun;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EvaluateWorkflows
{
    public function __construct(
        private readonly EnsureWorkflowCatalog $catalog,
        private readonly DispatchFamilyMessage $messages,
    ) {}

    /**
     * @return list<WorkflowRun>
     */
    public function execute(string $schoolId, ?DateTimeInterface $asOf = null, bool $forceLive = false): array
    {
        $this->catalog->execute($schoolId);
        $asOf = CarbonImmutable::parse($asOf ?? 'now');
        $today = QuietHours::today($asOf);
        $quiet = QuietHours::isQuiet($asOf);
        $school = School::query()->find($schoolId);
        $schoolName = $school?->name ?? 'l’école';

        $runs = [];
        foreach (WorkflowRule::query()->where('enabled', true)->orderBy('template_key')->get() as $rule) {
            $live = $forceLive ? true : ! $rule->dry_run;
            $cap = min(
                (int) $rule->daily_action_cap,
                (int) config('fanabe.workflow.max_daily_actions', 50),
            );
            $used = $this->actionsUsedToday($rule, $today);

            foreach ($this->matches($rule, $asOf) as $match) {
                $idempotencyKey = $rule->template_key.':'.$match['enrollment']->id.':'.$today;
                $run = $this->runOnce($schoolId, $rule, $match, $idempotencyKey, $live, $quiet, $schoolName, $cap, $used);
                if ($run === null) {
                    continue;
                }
                $runs[] = $run;
                if ($live && in_array($run->status, ['completed', 'capped'], true)) {
                    $used++;
                }
            }
        }

        return $runs;
    }

    /**
     * @param  array{enrollment: Enrollment, reason: string, title: string, priority: string, remaining: int, facts: array<string, mixed>}  $match
     */
    private function runOnce(
        string $schoolId,
        WorkflowRule $rule,
        array $match,
        string $idempotencyKey,
        bool $live,
        bool $quiet,
        string $schoolName,
        int $cap,
        int $used,
    ): ?WorkflowRun {
        try {
            return DB::transaction(function () use (
                $schoolId,
                $rule,
                $match,
                $idempotencyKey,
                $live,
                $quiet,
                $schoolName,
                $cap,
                $used,
            ): WorkflowRun {
                $existing = WorkflowRun::query()
                    ->where('rule_id', $rule->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }

                $status = 'completed';
                if (! $live) {
                    $status = 'simulated';
                } elseif ($used >= $cap) {
                    $status = 'capped';
                }

                $run = WorkflowRun::query()->create([
                    'school_id' => $schoolId,
                    'rule_id' => $rule->id,
                    'trigger_event_type' => 'scheduled',
                    'subject_type' => 'enrollment',
                    'subject_id' => $match['enrollment']->id,
                    'idempotency_key' => $idempotencyKey,
                    'status' => $status,
                    'started_at' => now(),
                ]);

                if ($status === 'capped') {
                    WorkflowAction::query()->create([
                        'school_id' => $schoolId,
                        'run_id' => $run->id,
                        'type' => 'skip',
                        'status' => 'capped',
                        'payload' => ['reason' => 'daily_cap'],
                    ]);
                    $run->forceFill(['finished_at' => now()])->save();

                    return $run;
                }

                if (! $live) {
                    WorkflowAction::query()->create([
                        'school_id' => $schoolId,
                        'run_id' => $run->id,
                        'type' => 'simulate',
                        'status' => 'simulated',
                        'payload' => [
                            'title' => $match['title'],
                            'reason' => $match['reason'],
                            'channels' => ['in_app', 'print'],
                        ],
                    ]);
                    $run->forceFill(['finished_at' => now()])->save();

                    return $run;
                }

                $open = CollectionTask::query()
                    ->where('enrollment_id', $match['enrollment']->id)
                    ->where('template_key', $rule->template_key)
                    ->whereIn('status', ['open', 'in_progress'])
                    ->first();

                if ($open === null) {
                    $open = CollectionTask::query()->create([
                        'school_id' => $schoolId,
                        'enrollment_id' => $match['enrollment']->id,
                        'template_key' => $rule->template_key,
                        'title' => $match['title'],
                        'reason_summary' => $match['reason'],
                        'priority' => $match['priority'],
                        'status' => 'open',
                        'workflow_run_id' => $run->id,
                    ]);
                    WorkflowAction::query()->create([
                        'school_id' => $schoolId,
                        'run_id' => $run->id,
                        'type' => 'create_task',
                        'status' => 'completed',
                        'payload' => ['task_id' => $open->id],
                    ]);
                }

                $this->notifyFamily($schoolId, $rule, $match, $run, $quiet, $schoolName);

                $run->forceFill(['finished_at' => now()])->save();

                return $run;
            });
        } catch (UniqueConstraintViolationException) {
            return WorkflowRun::query()
                ->where('rule_id', $rule->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
        }
    }

    /**
     * @param  array{enrollment: Enrollment, remaining: int, facts: array<string, mixed>}  $match
     */
    private function notifyFamily(
        string $schoolId,
        WorkflowRule $rule,
        array $match,
        WorkflowRun $run,
        bool $quiet,
        string $schoolName,
    ): void {
        $student = $match['enrollment']->person;
        if ($student === null) {
            $match['enrollment']->load('person');
            $student = $match['enrollment']->person;
        }
        if ($student === null) {
            return;
        }

        $variables = [
            'student_first_name' => $student->first_name,
            'student_last_name' => $student->last_name,
            'school_name' => $schoolName,
            'remaining_amount' => (string) $match['remaining'],
        ];

        $deliverInApp = ! $quiet;
        foreach (FamilyRecipients::adultsForStudent((string) $student->id) as $adult) {
            foreach (['in_app', 'print'] as $channel) {
                $deliverNow = $channel === 'print' || $deliverInApp;
                $message = $this->messages->execute(
                    schoolId: $schoolId,
                    templateKey: $rule->template_key,
                    channel: $channel,
                    subjectPersonId: (string) $student->id,
                    recipientPersonId: (string) $adult->id,
                    variables: $variables,
                    idempotencyKey: $rule->template_key.':'.$channel.':'.$match['enrollment']->id.':'.$adult->id.':'.QuietHours::today(),
                    deliverNow: $deliverNow,
                    workflowRunId: (string) $run->id,
                    priority: $match['priority'],
                );
                if ($message !== null) {
                    WorkflowAction::query()->create([
                        'school_id' => $schoolId,
                        'run_id' => $run->id,
                        'type' => 'notify',
                        'status' => 'completed',
                        'payload' => ['message_id' => $message->id, 'channel' => $channel],
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array{enrollment: Enrollment, reason: string, title: string, priority: string, remaining: int, facts: array<string, mixed>}>
     */
    private function matches(WorkflowRule $rule, CarbonImmutable $asOf): array
    {
        $enrollments = Enrollment::query()
            ->with('person')
            ->where('status', EnrollmentStatus::Active)
            ->get();

        $matches = [];
        foreach ($enrollments as $enrollment) {
            $match = match ($rule->template_key) {
                'payment_overdue' => $this->matchPaymentOverdue($enrollment, $rule, $asOf),
                'repeated_absence' => $this->matchRepeatedAbsence($enrollment, $rule, $asOf),
                'missing_document' => $this->matchMissingDocument($enrollment, $rule),
                default => null,
            };
            if ($match !== null) {
                $matches[] = $match;
            }
        }

        usort($matches, function (array $a, array $b): int {
            $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

            return ($rank[$a['priority']] ?? 9) <=> ($rank[$b['priority']] ?? 9);
        });

        return $matches;
    }

    /**
     * @return array{enrollment: Enrollment, reason: string, title: string, priority: string, remaining: int, facts: array<string, mixed>}|null
     */
    private function matchPaymentOverdue(Enrollment $enrollment, WorkflowRule $rule, CarbonImmutable $asOf): ?array
    {
        $minDays = (int) ($rule->params['min_days_overdue'] ?? 8);
        $assessment = RiskAssessment::query()->where('enrollment_id', $enrollment->id)->first();
        $remaining = $assessment?->outstanding_amount ?? EnrollmentInstallments::remaining((string) $enrollment->id);
        $days = $assessment?->days_overdue ?? 0;
        if ($remaining <= 0 || $days < $minDays) {
            return null;
        }

        $level = $assessment?->effectiveLevel() ?? RiskLevel::Medium;
        $name = trim(($enrollment->person?->first_name ?? '').' '.($enrollment->person?->last_name ?? ''));

        return [
            'enrollment' => $enrollment,
            'title' => 'Relancer la famille de '.$name.' — écolage en retard',
            'reason' => "Échéance impayée depuis {$days} jours — ".number_format($remaining, 0, ',', ' ').' Ar restants.',
            'priority' => $level->value,
            'remaining' => (int) $remaining,
            'facts' => ['days_overdue' => $days, 'remaining' => $remaining],
        ];
    }

    /**
     * @return array{enrollment: Enrollment, reason: string, title: string, priority: string, remaining: int, facts: array<string, mixed>}|null
     */
    private function matchRepeatedAbsence(Enrollment $enrollment, WorkflowRule $rule, CarbonImmutable $asOf): ?array
    {
        $min = (int) ($rule->params['min_absences'] ?? 3);
        $window = (int) ($rule->params['window_days'] ?? 7);
        $from = $asOf->subDays($window - 1)->toDateString();
        $dates = AttendanceRecord::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('status', AttendanceStatus::Absent)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $asOf->toDateString())
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date): string => $date->toDateString())
            ->unique()
            ->values();

        if ($dates->count() < $min) {
            return null;
        }

        $name = trim(($enrollment->person?->first_name ?? '').' '.($enrollment->person?->last_name ?? ''));
        $list = $dates->implode(', ');

        return [
            'enrollment' => $enrollment,
            'title' => 'Contacter la famille de '.$name.' — absences répétées',
            'reason' => $dates->count()." absences sur les {$window} derniers jours ({$list}).",
            'priority' => 'high',
            'remaining' => 0,
            'facts' => ['absence_dates' => $dates->all()],
        ];
    }

    /**
     * @return array{enrollment: Enrollment, reason: string, title: string, priority: string, remaining: int, facts: array<string, mixed>}|null
     */
    private function matchMissingDocument(Enrollment $enrollment, WorkflowRule $rule): ?array
    {
        $type = (string) ($rule->params['document_type'] ?? 'birth_certificate');
        $exists = FanabeDocument::query()
            ->where('owner_person_id', $enrollment->person_id)
            ->where('type', $type)
            ->exists();
        if ($exists) {
            return null;
        }

        $name = trim(($enrollment->person?->first_name ?? '').' '.($enrollment->person?->last_name ?? ''));

        return [
            'enrollment' => $enrollment,
            'title' => 'Demander un document pour '.$name,
            'reason' => 'Un acte de naissance n’est pas encore au dossier.',
            'priority' => 'medium',
            'remaining' => 0,
            'facts' => ['document_type' => $type],
        ];
    }

    private function actionsUsedToday(WorkflowRule $rule, string $today): int
    {
        return WorkflowAction::query()
            ->whereHas('run', fn ($query) => $query->where('rule_id', $rule->id))
            ->whereDate('created_at', $today)
            ->whereNotIn('status', ['simulated', 'capped'])
            ->count();
    }
}
