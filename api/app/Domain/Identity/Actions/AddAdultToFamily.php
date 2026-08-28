<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Family\Models\FamilyMember;
use App\Domain\Family\Support\FamilyHasSchoolEnrollment;
use App\Domain\Identity\Enums\PersonRoleType;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Enums\SchoolPersonLinkKind;
use App\Domain\Identity\Enums\SchoolPersonLinkSource;
use App\Domain\Identity\Models\ParentInvitation;
use App\Domain\Identity\Models\Person;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Support\InvitationCode;
use App\Domain\Platform\Support\PhoneNumber;
use App\Domain\Platform\Support\SecretHash;
use Illuminate\Support\Facades\DB;

final class AddAdultToFamily
{
    /**
     * @param  array<string, mixed>  $adult
     * @return array{adult: Person, invitation_code: ?string}
     */
    public function execute(
        string $schoolId,
        string $familyId,
        string $actorPersonId,
        array $adult,
        RelationshipType $relationship = RelationshipType::ParentOf,
    ): array {
        if (! FamilyHasSchoolEnrollment::exists($familyId)) {
            throw new DomainException('Foyer introuvable.', 404);
        }

        return DB::transaction(function () use ($schoolId, $familyId, $actorPersonId, $adult, $relationship): array {
            $person = Person::createWithUniquePublicId([
                'first_name' => $adult['first_name'],
                'last_name' => $adult['last_name'],
                'birth_date' => $adult['birth_date'] ?? null,
                'sex' => $adult['sex'] ?? 'unspecified',
                'preferred_language' => $adult['preferred_language'] ?? 'fr',
                'phone_e164' => isset($adult['phone']) && $adult['phone'] !== '' && $adult['phone'] !== null
                    ? PhoneNumber::parse((string) $adult['phone'])->e164()
                    : null,
                'email' => $adult['email'] ?? null,
            ]);

            $role = match ($relationship) {
                RelationshipType::GuardianOf => PersonRoleType::Guardian,
                RelationshipType::FinancialContactFor => PersonRoleType::FinancialContact,
                default => PersonRoleType::Parent,
            };
            app(AcquirePersonRole::class)->execute($person->id, $role);

            FamilyMember::query()->create([
                'family_id' => $familyId,
                'person_id' => $person->id,
                'role_in_family' => 'adult',
                'joined_at' => now(),
            ]);

            $children = FamilyMember::query()
                ->where('family_id', $familyId)
                ->where('role_in_family', 'child')
                ->whereNull('left_at')
                ->pluck('person_id');

            foreach ($children as $childId) {
                app(EstablishRelationship::class)->execute(
                    $person->id,
                    (string) $childId,
                    $relationship,
                    verifiedByPersonId: $actorPersonId,
                );
            }

            $needsPortal = in_array($relationship, [
                RelationshipType::ParentOf,
                RelationshipType::GuardianOf,
                RelationshipType::FinancialContactFor,
            ], true);

            if ($needsPortal) {
                app(GrantSchoolPersonLink::class)->execute(
                    $schoolId,
                    $person->id,
                    SchoolPersonLinkKind::Parent,
                    SchoolPersonLinkSource::Created,
                    grantsContactAccess: $relationship !== RelationshipType::FinancialContactFor,
                );
            }

            $code = null;
            if ($needsPortal) {
                $code = InvitationCode::generate();
                ParentInvitation::query()->create([
                    'school_id' => $schoolId,
                    'person_id' => $person->id,
                    'code_hash' => SecretHash::make($code),
                    'created_by_person_id' => $actorPersonId,
                    'expires_at' => now()->addDays(30),
                ]);
            }

            Auditor::record('family.adult_added', 'family', $familyId, $person->id, [
                'relationship' => $relationship->value,
            ]);

            return [
                'adult' => $person,
                'invitation_code' => $code,
            ];
        });
    }
}
