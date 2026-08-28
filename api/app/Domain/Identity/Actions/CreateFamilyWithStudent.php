<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Enrollment\Actions\EnrollStudent;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Family\Models\Family;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Models\ParentInvitation;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Support\InvitationCode;
use App\Domain\Platform\Support\PhoneNumber;
use App\Domain\Platform\Support\SecretHash;
use App\Domain\Reliability\Models\TrustEvent;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Support\Facades\DB;

final class CreateFamilyWithStudent
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     birth_date?: ?string,
     *     birth_date_precision?: ?string,
     *     sex?: ?string,
     *     phone?: ?string,
     *     email?: ?string,
     *     preferred_language?: ?string
     * }  $parent
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     birth_date?: ?string,
     *     birth_date_precision?: ?string,
     *     sex?: ?string,
     *     preferred_language?: ?string
     * }  $student
     * @return array{
     *     family: Family,
     *     parent: Person,
     *     student: Person,
     *     invitation_code: string,
     *     enrollment: Enrollment
     * }
     */
    public function execute(
        string $schoolId,
        string $schoolYearId,
        string $actorPersonId,
        array $parent,
        array $student,
        RelationshipType $relationship = RelationshipType::ParentOf,
        ?string $studentNumber = null,
        ?string $familyLabel = null,
        ?string $classroomId = null,
    ): array {
        SchoolYear::query()->findOrFail($schoolYearId);

        return DB::transaction(function () use (
            $schoolId,
            $schoolYearId,
            $actorPersonId,
            $parent,
            $student,
            $relationship,
            $studentNumber,
            $familyLabel,
            $classroomId,
        ): array {
            $parentPerson = Person::createWithUniquePublicId($this->civilAttributes($parent, includeContacts: true));
            app(AcquirePersonRole::class)->execute($parentPerson->id, PersonRoleType::Parent);

            $studentPerson = Person::createWithUniquePublicId($this->civilAttributes($student, includeContacts: false));
            app(AcquirePersonRole::class)->execute($studentPerson->id, PersonRoleType::Student);

            app(EstablishRelationship::class)->execute(
                $parentPerson->id,
                $studentPerson->id,
                $relationship,
                verifiedByPersonId: $actorPersonId,
            );

            $trimmedLabel = is_string($familyLabel) ? trim($familyLabel) : '';
            $family = Family::query()->create([
                'label' => $trimmedLabel !== ''
                    ? $trimmedLabel
                    : (string) ($student['last_name'] ?? $parentPerson->last_name),
                'primary_language' => $parentPerson->preferred_language ?? 'fr',
                'created_by_person_id' => $actorPersonId,
            ]);

            FamilyMember::query()->create([
                'family_id' => $family->id,
                'person_id' => $parentPerson->id,
                'role_in_family' => 'adult',
                'joined_at' => now(),
            ]);
            FamilyMember::query()->create([
                'family_id' => $family->id,
                'person_id' => $studentPerson->id,
                'role_in_family' => 'child',
                'joined_at' => now(),
            ]);

            $grant = app(GrantSchoolPersonLink::class);
            $grant->execute(
                $schoolId,
                $parentPerson->id,
                SchoolPersonLinkKind::Parent,
                SchoolPersonLinkSource::Created,
                grantsContactAccess: true,
            );
            $grant->execute(
                $schoolId,
                $studentPerson->id,
                SchoolPersonLinkKind::Student,
                SchoolPersonLinkSource::Created,
                grantsContactAccess: false,
            );

            $code = InvitationCode::generate();
            ParentInvitation::query()->create([
                'school_id' => $schoolId,
                'person_id' => $parentPerson->id,
                'code_hash' => SecretHash::make($code),
                'created_by_person_id' => $actorPersonId,
                'expires_at' => now()->addDays(30),
            ]);

            $enrollment = app(EnrollStudent::class)->execute(
                schoolId: $schoolId,
                schoolYearId: $schoolYearId,
                studentPersonId: $studentPerson->id,
                actorPersonId: $actorPersonId,
                studentNumber: $studentNumber,
                skipAuthorization: true,
                classroomId: $classroomId,
            );

            Auditor::record('family.created', 'family', $family->id, $parentPerson->id);
            TrustEvent::emit('person', $parentPerson->id, 'family.created', $schoolId, 'family', $family->id);

            return [
                'family' => $family,
                'parent' => $parentPerson,
                'student' => $studentPerson,
                'invitation_code' => $code,
                'enrollment' => $enrollment,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function civilAttributes(array $input, bool $includeContacts): array
    {
        $attributes = [
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'birth_date' => $input['birth_date'] ?? null,
            'birth_date_precision' => $input['birth_date_precision'] ?? null,
            'sex' => $input['sex'] ?? 'unspecified',
            'preferred_language' => $input['preferred_language'] ?? 'fr',
        ];

        if ($includeContacts) {
            $attributes['phone_e164'] = isset($input['phone']) && $input['phone'] !== '' && $input['phone'] !== null
                ? PhoneNumber::parse((string) $input['phone'])->e164()
                : null;
            $attributes['email'] = $input['email'] ?? null;
        }

        return $attributes;
    }
}
