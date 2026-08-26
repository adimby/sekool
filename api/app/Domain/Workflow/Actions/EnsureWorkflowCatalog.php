<?php

namespace App\Domain\Workflow\Actions;

use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Communication\Support\MessageCatalog;
use App\Domain\Workflow\Models\WorkflowRule;

final class EnsureWorkflowCatalog
{
    public const TEMPLATES = ['payment_overdue', 'repeated_absence', 'missing_document'];

    public function execute(string $schoolId, bool $live = false): void
    {
        $defaults = [
            'payment_overdue' => ['min_days_overdue' => 8],
            'repeated_absence' => ['min_absences' => 3, 'window_days' => 7],
            'missing_document' => ['document_type' => 'birth_certificate'],
        ];

        foreach (self::TEMPLATES as $key) {
            WorkflowRule::query()->firstOrCreate(
                ['school_id' => $schoolId, 'template_key' => $key],
                [
                    'enabled' => $key !== 'missing_document',
                    'params' => $defaults[$key],
                    'version' => 1,
                    'dry_run' => ! $live,
                    'daily_action_cap' => (int) config('fanabe.workflow.default_daily_actions', 20),
                    'quiet_hours' => [
                        'start' => (int) config('fanabe.workflow.quiet_hours_start', 20),
                        'end' => (int) config('fanabe.workflow.quiet_hours_end', 7),
                        'timezone' => (string) config('fanabe.workflow.timezone', 'Indian/Antananarivo'),
                    ],
                ],
            );
        }

        if ($live) {
            WorkflowRule::query()
                ->where('school_id', $schoolId)
                ->whereIn('template_key', self::TEMPLATES)
                ->update(['dry_run' => false]);
        }

        foreach (MessageCatalog::defaults() as $template) {
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
