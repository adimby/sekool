<?php

namespace App\Domain\Platform\Demo;

use App\Domain\Academic\Actions\RecordAttendance;
use App\Domain\Academic\Enums\AttendanceSession;
use App\Domain\Academic\Enums\AttendanceStatus;
use App\Domain\Collection\Actions\RecomputeCollection;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Family\Models\Family;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Finance\Actions\GenerateInvoice;
use App\Domain\Finance\Models\Installment;
use App\Domain\Identity\Actions\AcquirePersonRole;
use App\Domain\Identity\Actions\EstablishRelationship;
use App\Domain\Identity\Actions\GrantSchoolPersonLink;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Enums\Sex;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\School;
use App\Domain\Workflow\Actions\EnsureWorkflowCatalog;
use Carbon\CarbonImmutable;

final class EnsureCollection
{
    public function execute(): void
    {
        TenantContext::runWithRlsBypass(function (): void {
            $antsahabe = School::query()->where('code', 'antsahabe')->first();
            if ($antsahabe !== null) {
                TenantContext::run(
                    TenantContext::forSchool((string) $antsahabe->id),
                    fn () => $this->seedAntsahabe($antsahabe),
                );
            }

            foreach (School::query()->where('code', '!=', 'antsahabe')->get() as $school) {
                TenantContext::run(
                    TenantContext::forSchool((string) $school->id),
                    fn () => app(EnsureWorkflowCatalog::class)->execute((string) $school->id),
                );
            }
        });
    }

    private function seedAntsahabe(School $school): void
    {
        $actorId = UserAccount::query()
            ->whereRaw('lower(email) = ?', ['direction.antsahabe@fanabe.test'])
            ->value('person_id');
        $teacherId = UserAccount::query()
            ->whereRaw('lower(email) = ?', ['teacher.antsahabe@fanabe.test'])
            ->value('person_id') ?? $actorId;

        if ($actorId === null) {
            return;
        }

        $enrollments = Enrollment::query()
            ->with('person')
            ->where('status', EnrollmentStatus::Active)
            ->get()
            ->keyBy(fn (Enrollment $row): string => (string) $row->person?->first_name);

        $tojo = $enrollments->get('Tojo');
        if ($tojo !== null) {
            $this->ensureTojoFamily($school, $tojo);
        }

        $invoices = app(GenerateInvoice::class);
        foreach (['Fanja', 'Hery'] as $firstName) {
            $enrollment = $enrollments->get($firstName);
            if ($enrollment === null) {
                continue;
            }
            $invoices->execute((string) $school->id, (string) $enrollment->id, $actorId);
        }

        $today = CarbonImmutable::now();
        $this->backdateFirstInstallment($enrollments->get('Fanja'), $today->subDays(70)->toDateString());
        $this->backdateFirstInstallment($enrollments->get('Hery'), $today->subDays(16)->toDateString());

        if ($tojo !== null && $teacherId !== null) {
            $attendance = app(RecordAttendance::class);
            foreach ([0, 1, 2] as $daysAgo) {
                $attendance->execute(
                    schoolId: (string) $school->id,
                    enrollmentId: (string) $tojo->id,
                    date: $today->subDays($daysAgo)->toDateString(),
                    session: AttendanceSession::FullDay,
                    status: AttendanceStatus::Absent,
                    recordedByPersonId: $teacherId,
                    clientReference: sprintf('44444444-4444-4444-8444-44444444444%d', $daysAgo),
                    recordedVia: 'seed',
                );
            }
        }

        app(RecomputeCollection::class)->execute((string) $school->id, live: true);
    }

    private function backdateFirstInstallment(?Enrollment $enrollment, string $dueOn): void
    {
        if ($enrollment === null) {
            return;
        }

        $installment = Installment::query()
            ->whereHas('invoice', fn ($query) => $query->where('enrollment_id', $enrollment->id))
            ->orderBy('sequence')
            ->first();

        if ($installment === null) {
            return;
        }

        $installment->forceFill(['due_on' => $dueOn])->save();
        $installment->refreshDerivedStatus();
        $installment->save();
    }

    private function ensureTojoFamily(School $school, Enrollment $enrollment): void
    {
        if (FamilyMember::query()->where('person_id', $enrollment->person_id)->whereNull('left_at')->exists()) {
            return;
        }

        $parent = Person::query()->where('email', 'parent.tojo@fanabe.test')->first()
            ?? Person::createWithUniquePublicId([
                'first_name' => 'Hanitra',
                'last_name' => 'Andrianina',
                'birth_date' => '1982-02-14',
                'sex' => Sex::Female,
                'email' => 'parent.tojo@fanabe.test',
            ]);

        app(AcquirePersonRole::class)->execute($parent->id, PersonRoleType::Parent);
        app(EstablishRelationship::class)->execute($parent->id, (string) $enrollment->person_id, RelationshipType::ParentOf);
        app(GrantSchoolPersonLink::class)->execute(
            (string) $school->id,
            $parent->id,
            SchoolPersonLinkKind::Parent,
            SchoolPersonLinkSource::Created,
            true,
        );

        $family = Family::query()->create([
            'label' => 'Andrianina',
            'primary_language' => 'fr',
            'created_by_person_id' => $parent->id,
        ]);
        FamilyMember::query()->create([
            'family_id' => $family->id,
            'person_id' => $parent->id,
            'role_in_family' => 'adult',
            'joined_at' => now(),
        ]);
        FamilyMember::query()->create([
            'family_id' => $family->id,
            'person_id' => $enrollment->person_id,
            'role_in_family' => 'child',
            'joined_at' => now(),
        ]);
    }
}
