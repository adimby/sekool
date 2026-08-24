<?php

namespace Database\Seeders;

use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\EnrollmentStatusChange;
use App\Domain\Family\Models\Family;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Identity\Actions\AcquirePersonRole;
use App\Domain\Identity\Actions\EstablishRelationship;
use App\Domain\Identity\Actions\GrantSchoolPersonLink;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Enums\Sex;
use App\Domain\Identity\Models\ExternalEducationPeriod;
use App\Domain\Identity\Models\FanabeDocument;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\UserAccount;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Seeder;

/**
 * Brief §8 personas A, B and C — deterministic identity proofs.
 */
class PersonaSeeder extends Seeder
{
    public function run(): void
    {
        $antsahabe = School::query()->where('code', 'antsahabe')->firstOrFail();
        $ambohipo = School::query()->where('code', 'ambohipo')->firstOrFail();

        $year2016 = $this->year($antsahabe, '2015-2016', '2015-09-01', '2016-07-15', false);
        $year2023 = $this->year($antsahabe, '2022-2023', '2022-09-01', '2023-07-15', false);
        $year2025 = $this->year($antsahabe, '2024-2025', '2024-09-01', '2025-07-15', false);
        $yearCurrentA = SchoolYear::query()->where('school_id', $antsahabe->id)->where('is_current', true)->firstOrFail();
        $yearCurrentB = SchoolYear::query()->where('school_id', $ambohipo->id)->where('is_current', true)->firstOrFail();

        $roles = app(AcquirePersonRole::class);
        $relate = app(EstablishRelationship::class);
        $link = app(GrantSchoolPersonLink::class);

        // Person A — Andry Rasoanaivo, alumni of Antsahabe, now parent of two children in two schools.
        $andry = Person::createWithUniquePublicId([
            'first_name' => 'Andry',
            'last_name' => 'Rasoanaivo',
            'birth_date' => '1990-03-12',
            'birth_date_precision' => 'exact',
            'sex' => Sex::Male,
            'phone_e164' => '+261341010101',
            'email' => 'parent.andry@fanabe.test',
        ]);
        $roles->execute($andry->id, PersonRoleType::Student, new \DateTimeImmutable('2005-09-01'));
        $roles->close($andry->id, PersonRoleType::Student, new \DateTimeImmutable('2016-07-15'));
        $roles->execute($andry->id, PersonRoleType::Alumni, new \DateTimeImmutable('2016-07-16'));
        $roles->execute($andry->id, PersonRoleType::Parent, new \DateTimeImmutable('2024-01-10'));

        UserAccount::query()->create([
            'person_id' => $andry->id,
            'email' => 'parent.andry@fanabe.test',
            'password' => 'password',
        ]);

        $this->enroll($antsahabe, $year2016, $andry, EnrollmentStatus::Graduated, '2015-09-01', '2016-07-15', 'graduated');
        $link->execute($antsahabe->id, $andry->id, SchoolPersonLinkKind::Parent, SchoolPersonLinkSource::Created, true);
        $link->execute($ambohipo->id, $andry->id, SchoolPersonLinkKind::Parent, SchoolPersonLinkSource::Created, true);

        $hery = Person::createWithUniquePublicId([
            'first_name' => 'Hery',
            'last_name' => 'Rasoanaivo',
            'birth_date' => '2014-05-08',
            'sex' => Sex::Male,
        ]);
        $voahirana = Person::createWithUniquePublicId([
            'first_name' => 'Voahirana',
            'last_name' => 'Rasoanaivo',
            'birth_date' => '2016-11-21',
            'sex' => Sex::Female,
        ]);
        $roles->execute($hery->id, PersonRoleType::Student);
        $roles->execute($voahirana->id, PersonRoleType::Student);
        $relate->execute($andry->id, $hery->id, RelationshipType::ParentOf);
        $relate->execute($andry->id, $voahirana->id, RelationshipType::ParentOf);

        $familyA = Family::query()->create([
            'label' => 'Rasoanaivo',
            'primary_language' => 'fr',
            'created_by_person_id' => $andry->id,
        ]);
        foreach ([$andry, $hery, $voahirana] as $i => $member) {
            FamilyMember::query()->create([
                'family_id' => $familyA->id,
                'person_id' => $member->id,
                'role_in_family' => $i === 0 ? 'adult' : 'child',
                'joined_at' => now(),
            ]);
        }

        $link->execute($antsahabe->id, $hery->id, SchoolPersonLinkKind::Student, SchoolPersonLinkSource::Created, false);
        $link->execute($ambohipo->id, $voahirana->id, SchoolPersonLinkKind::Student, SchoolPersonLinkSource::Created, false);
        $this->enroll($antsahabe, $yearCurrentA, $hery, EnrollmentStatus::Active, '2026-09-01');
        $this->enroll($ambohipo, $yearCurrentB, $voahirana, EnrollmentStatus::Active, '2026-09-01');

        // Person B — Fanja Rakoto, active student at Antsahabe; parent D = Mialy Rakoto.
        $mialy = Person::createWithUniquePublicId([
            'first_name' => 'Mialy',
            'last_name' => 'Rakoto',
            'birth_date' => '1988-07-19',
            'sex' => Sex::Female,
            'phone_e164' => '+261342020202',
            'email' => 'parent.d@fanabe.test',
        ]);
        $fanja = Person::createWithUniquePublicId([
            'first_name' => 'Fanja',
            'last_name' => 'Rakoto',
            'birth_date' => '2013-04-02',
            'sex' => Sex::Female,
        ]);
        $roles->execute($mialy->id, PersonRoleType::Parent);
        $roles->execute($fanja->id, PersonRoleType::Student);
        $relate->execute($mialy->id, $fanja->id, RelationshipType::ParentOf);
        UserAccount::query()->create([
            'person_id' => $mialy->id,
            'email' => 'parent.d@fanabe.test',
            'password' => 'password',
        ]);
        $familyB = Family::query()->create(['label' => 'Rakoto', 'primary_language' => 'fr', 'created_by_person_id' => $mialy->id]);
        FamilyMember::query()->create(['family_id' => $familyB->id, 'person_id' => $mialy->id, 'role_in_family' => 'adult', 'joined_at' => now()]);
        FamilyMember::query()->create(['family_id' => $familyB->id, 'person_id' => $fanja->id, 'role_in_family' => 'child', 'joined_at' => now()]);
        $link->execute($antsahabe->id, $mialy->id, SchoolPersonLinkKind::Parent, SchoolPersonLinkSource::Created, true);
        $link->execute($antsahabe->id, $fanja->id, SchoolPersonLinkKind::Student, SchoolPersonLinkSource::Created, false);
        $this->enroll($antsahabe, $yearCurrentA, $fanja, EnrollmentStatus::Active, '2026-09-01', studentNumber: 'ANT-5E-014');

        // Person C — Tojo Andrianina, Antsahabe → Lycée Saint-Michel (external) → back to Antsahabe.
        $tojo = Person::createWithUniquePublicId([
            'first_name' => 'Tojo',
            'last_name' => 'Andrianina',
            'birth_date' => '2008-09-30',
            'sex' => Sex::Male,
        ]);
        $roles->execute($tojo->id, PersonRoleType::Student, new \DateTimeImmutable('2022-09-01'));
        $this->enroll($antsahabe, $year2023, $tojo, EnrollmentStatus::Withdrawn, '2022-09-01', '2023-07-15', 'external_period');
        ExternalEducationPeriod::query()->create([
            'person_id' => $tojo->id,
            'school_label' => 'Lycée Saint-Michel',
            'starts_on' => '2023-09-01',
            'ends_on' => '2024-07-15',
            'declared_grade_level' => '4ème',
            'declared_by_person_id' => $andry->id,
            'verification_status' => 'unverified',
            'notes' => 'Déclaré par la famille — hors réseau FANABE',
        ]);
        $bulletin = FanabeDocument::query()->create([
            'school_id' => null,
            'owner_person_id' => $tojo->id,
            'type' => 'report_card',
            'source_type' => 'external',
            'source_school_label' => 'Lycée Saint-Michel',
            'verification_status' => 'unverified',
            'uploaded_by_person_id' => $andry->id,
            'uploaded_at' => '2024-08-20 08:00:00',
        ]);
        $bulletin->forceFill(['verification_status' => 'attested_by_school'])->save();
        $this->enroll($antsahabe, $year2025, $tojo, EnrollmentStatus::Graduated, '2024-09-01', '2025-07-15', 'year_end');
        $this->enroll($antsahabe, $yearCurrentA, $tojo, EnrollmentStatus::Active, '2026-09-01');
        $link->execute($antsahabe->id, $tojo->id, SchoolPersonLinkKind::Student, SchoolPersonLinkSource::Created, false);
    }

    private function year(School $school, string $label, string $starts, string $ends, bool $current): SchoolYear
    {
        return SchoolYear::query()->firstOrCreate(
            ['school_id' => $school->id, 'label' => $label],
            ['starts_on' => $starts, 'ends_on' => $ends, 'is_current' => $current],
        );
    }

    private function enroll(
        School $school,
        SchoolYear $year,
        Person $person,
        EnrollmentStatus $status,
        string $enrolledOn,
        ?string $endedOn = null,
        ?string $exitReason = null,
        ?string $studentNumber = null,
    ): Enrollment {
        $enrollment = Enrollment::query()->create([
            'school_id' => $school->id,
            'school_year_id' => $year->id,
            'person_id' => $person->id,
            'student_number' => $studentNumber,
            'status' => $status,
            'enrolled_on' => $enrolledOn,
            'ended_on' => $endedOn,
            'exit_reason' => $exitReason,
            'source_type' => 'native',
        ]);

        EnrollmentStatusChange::query()->create([
            'school_id' => $school->id,
            'enrollment_id' => $enrollment->id,
            'from_status' => null,
            'to_status' => $status->value,
            'reason' => $exitReason ?? 'inscription',
            'occurred_at' => $enrolledOn,
            'actor_person_id' => $person->id,
        ]);

        return $enrollment;
    }
}
