<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Enrollment\Actions\EnrollStudent;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Family\Support\FamilyHasSchoolEnrollment;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\Relationship;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class AddChildToFamily
{
    /**
     * @param  array<string, mixed>  $student
     * @return array{student: Person, enrollment: mixed}
     */
    public function execute(
        string $schoolId,
        string $schoolYearId,
        string $familyId,
        string $actorPersonId,
        array $student,
        ?string $adultPersonId = null,
        ?string $classroomId = null,
    ): array {
        if (! FamilyHasSchoolEnrollment::exists($familyId)) {
            throw new DomainException('Foyer introuvable.', 404);
        }

        return DB::transaction(function () use ($schoolId, $schoolYearId, $familyId, $actorPersonId, $student, $adultPersonId, $classroomId): array {
            $studentPerson = Person::createWithUniquePublicId([
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'birth_date' => $student['birth_date'] ?? null,
                'birth_date_precision' => $student['birth_date_precision'] ?? null,
                'sex' => $student['sex'] ?? 'unspecified',
                'preferred_language' => $student['preferred_language'] ?? 'fr',
            ]);
            app(AcquirePersonRole::class)->execute($studentPerson->id, PersonRoleType::Student);

            FamilyMember::query()->create([
                'family_id' => $familyId,
                'person_id' => $studentPerson->id,
                'role_in_family' => 'child',
                'joined_at' => now(),
            ]);

            $adults = FamilyMember::query()
                ->where('family_id', $familyId)
                ->where('role_in_family', 'adult')
                ->whereNull('left_at')
                ->pluck('person_id')
                ->all();

            if ($adultPersonId !== null) {
                if (! in_array($adultPersonId, $adults, true)) {
                    throw new DomainException('Cet adulte n’appartient pas au foyer.');
                }
                $adults = [$adultPersonId];
            }

            $existingChildId = FamilyMember::query()
                ->where('family_id', $familyId)
                ->where('role_in_family', 'child')
                ->where('person_id', '!=', $studentPerson->id)
                ->whereNull('left_at')
                ->value('person_id');

            foreach ($adults as $adultId) {
                $type = $this->relationshipTo($adultId, $existingChildId) ?? RelationshipType::ParentOf;
                if (in_array($type, [RelationshipType::ParentOf, RelationshipType::GuardianOf, RelationshipType::FinancialContactFor], true)) {
                    app(EstablishRelationship::class)->execute(
                        $adultId,
                        $studentPerson->id,
                        $type,
                        verifiedByPersonId: $actorPersonId,
                    );
                }
            }

            app(GrantSchoolPersonLink::class)->execute(
                $schoolId,
                $studentPerson->id,
                SchoolPersonLinkKind::Student,
                SchoolPersonLinkSource::Created,
                grantsContactAccess: false,
            );

            $enrollment = app(EnrollStudent::class)->execute(
                schoolId: $schoolId,
                schoolYearId: $schoolYearId,
                studentPersonId: $studentPerson->id,
                actorPersonId: $actorPersonId,
                skipAuthorization: true,
                classroomId: $classroomId ?? ($student['classroom_id'] ?? null),
            );

            Auditor::record('family.child_added', 'family', $familyId, $studentPerson->id);

            return [
                'student' => $studentPerson,
                'enrollment' => $enrollment,
            ];
        });
    }

    private function relationshipTo(string $adultId, ?string $childId): ?RelationshipType
    {
        if ($childId === null) {
            return RelationshipType::ParentOf;
        }

        $row = Relationship::query()
            ->where('subject_person_id', $adultId)
            ->where('object_person_id', $childId)
            ->where('status', 'active')
            ->first();

        return $row?->type instanceof RelationshipType ? $row->type : ($row !== null ? RelationshipType::tryFrom((string) $row->type) : null);
    }
}
