<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Academic\Actions\BulletinPayload;
use App\Domain\Academic\Models\AttendanceRecord;
use App\Domain\Certificate\Models\Certificate;
use App\Domain\Certificate\Support\CertificatePayload;
use App\Domain\Communication\Models\Message;
use App\Domain\Communication\Support\MessageRenderer;
use App\Domain\Consent\Models\Consent;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Identity\Support\PersonPayload;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\School;

final class ExportFamilyArchive
{
    public function __construct(private readonly BulletinPayload $bulletin) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $parentId): array
    {
        $parent = Person::query()->findOrFail($parentId);
        $childIds = ParentAuthorization::authorizedChildIds($parentId);

        $archive = TenantContext::runWithRlsBypass(function () use ($parent, $parentId, $childIds): array {
            $children = Person::query()->whereIn('id', $childIds)->orderBy('last_name')->get();
            $from = now()->subDays(14)->toDateString();

            $childRows = $children->map(function (Person $child) use ($from): array {
                $enrollments = Enrollment::query()
                    ->withoutGlobalScopes()
                    ->with(['school', 'classroom'])
                    ->where('person_id', $child->id)
                    ->where('status', EnrollmentStatus::Active)
                    ->get();

                $enrollmentIds = $enrollments->pluck('id');
                $attendance = AttendanceRecord::query()
                    ->withoutGlobalScopes()
                    ->whereIn('enrollment_id', $enrollmentIds)
                    ->where('date', '>=', $from)
                    ->orderByDesc('date')
                    ->get();

                $certificates = Certificate::query()
                    ->withoutGlobalScopes()
                    ->with(['enrollment.person', 'enrollment.classroom', 'subject'])
                    ->where('subject_person_id', $child->id)
                    ->orderByDesc('issued_at')
                    ->get();

                $invoices = Invoice::query()
                    ->withoutGlobalScopes()
                    ->whereIn('enrollment_id', $enrollmentIds)
                    ->where('status', '!=', 'cancelled')
                    ->get();

                $bulletin = null;
                $enrollment = $enrollments->first();
                if ($enrollment !== null) {
                    $bulletin = $this->bulletin->forEnrollment($enrollment);
                }

                return [
                    'person' => PersonPayload::forParent($child),
                    'enrollments' => $enrollments->map(fn (Enrollment $row): array => [
                        'school' => $row->school?->name,
                        'classroom' => $row->classroom?->name,
                        'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
                    ])->values(),
                    'attendance' => $attendance->map(fn (AttendanceRecord $row): array => [
                        'date' => $row->date?->toDateString(),
                        'status' => $row->status->value,
                        'reason' => $row->reason,
                        'justification' => $row->justification,
                    ])->values(),
                    'certificates' => $certificates->map(fn (Certificate $row): array => [
                        'type_label' => CertificatePayload::staff($row)['type_label'],
                        'public_reference' => $row->public_reference,
                        'status' => $row->effectiveStatus()->value,
                        'issued_at' => $row->issued_at?->toDateString(),
                    ])->values(),
                    'remaining_amount' => $invoices->sum(fn (Invoice $invoice): int => $invoice->remainingAmount()),
                    'bulletin' => $bulletin,
                ];
            })->values();

            $messages = Message::query()
                ->withoutGlobalScopes()
                ->where('recipient_person_id', $parentId)
                ->where('channel', 'in_app')
                ->whereIn('subject_person_id', $childIds)
                ->orderByDesc('queued_at')
                ->limit(100)
                ->get();

            $messageRows = $messages->map(function (Message $message): array {
                $subject = (string) ($message->payload['subject'] ?? '');
                $body = (string) ($message->payload['body'] ?? '');
                MessageRenderer::assertFamilySafe($subject, $body);

                return [
                    'queued_at' => $message->queued_at?->toIso8601String(),
                    'subject' => $subject,
                    'body' => $body,
                ];
            })->values();

            $consents = Consent::query()
                ->where('granted_by_person_id', $parentId)
                ->orderByDesc('granted_at')
                ->get();

            $schoolNames = School::query()
                ->whereIn('id', $consents->pluck('grantee_school_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            return [
                'notice' => 'Archive FANABE. Pas un LSU. FANABE n’envoie pas de SMS et n’encaisse rien en ligne.',
                'exported_at' => now()->toIso8601String(),
                'person' => PersonPayload::forParent($parent),
                'children' => $childRows,
                'messages' => $messageRows,
                'consents' => $consents->map(fn (Consent $row): array => [
                    'scope' => $row->scope instanceof \BackedEnum ? $row->scope->value : (string) $row->scope,
                    'purpose' => $row->purpose,
                    'active' => $row->isActive(),
                    'school' => $schoolNames->get($row->grantee_school_id)?->name,
                    'granted_at' => $row->granted_at?->toIso8601String(),
                ])->values(),
            ];
        });

        Auditor::record('family.exported', 'person', $parentId, $parentId);

        return $archive;
    }
}
