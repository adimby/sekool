<?php

namespace App\Http\Api\V1\School;

use App\Domain\Family\Models\Family;
use App\Domain\Family\Models\FamilyMember;
use App\Domain\Family\Support\FamilyHasSchoolEnrollment;
use App\Domain\Identity\Actions\AddAdultToFamily;
use App\Domain\Identity\Actions\AddChildToFamily;
use App\Domain\Identity\Actions\CreateFamilyWithStudent;
use App\Domain\Identity\Actions\FindSimilarPersons;
use App\Domain\Identity\Actions\ReissueParentInvitation;
use App\Domain\Identity\Actions\UpdatePersonCivilData;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Identity\Support\FamilyPayload;
use App\Domain\Identity\Support\PersonPayload;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FamilyController extends Controller
{
    public function index(): JsonResponse
    {
        $linkedIds = SchoolPersonLink::query()->pluck('person_id');
        $familyIds = FamilyMember::query()
            ->whereIn('person_id', $linkedIds)
            ->whereNull('left_at')
            ->pluck('family_id')
            ->unique();

        $families = Family::query()->whereIn('id', $familyIds)->orderBy('label')->get();

        return response()->json([
            'data' => $families->map(fn (Family $family): array => FamilyPayload::forSchool($family))->values(),
        ]);
    }

    public function show(string $school, string $family): JsonResponse
    {
        $this->guardFamily($family);
        $model = Family::query()->find($family);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => FamilyPayload::forSchool($model)]);
    }

    public function update(Request $request, string $school, string $family): JsonResponse
    {
        $this->guardFamily($family);
        $model = Family::query()->find($family);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:120'],
            'primary_language' => ['sometimes', 'string', 'max:8'],
        ]);

        $model->forceFill($data)->save();
        Auditor::record('family.updated', 'family', $model->id);

        return response()->json(['data' => FamilyPayload::forSchool($model->refresh())]);
    }

    public function addChild(Request $request, string $school, string $family, AddChildToFamily $add): JsonResponse
    {
        $this->guardFamily($family);
        $data = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date'],
            'sex' => ['nullable', 'string'],
            'adult_person_id' => ['nullable', 'uuid'],
            'classroom_id' => ['nullable', 'uuid'],
        ]);

        $result = $add->execute(
            TenantContext::requireSchoolId(),
            $data['school_year_id'],
            $family,
            $request->user()->person_id,
            $data,
            $data['adult_person_id'] ?? null,
            $data['classroom_id'] ?? null,
        );

        $link = SchoolPersonLink::query()
            ->where('person_id', $result['student']->id)
            ->where('kind', 'student')
            ->firstOrFail();

        return response()->json([
            'student' => PersonPayload::forSchool($result['student'], $link),
            'enrollment_id' => is_object($result['enrollment']) && isset($result['enrollment']->id)
                ? $result['enrollment']->id
                : null,
            'classroom_id' => is_object($result['enrollment']) && isset($result['enrollment']->classroom_id)
                ? $result['enrollment']->classroom_id
                : null,
        ], 201);
    }

    public function addAdult(Request $request, string $school, string $family, AddAdultToFamily $add): JsonResponse
    {
        $this->guardFamily($family);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'relationship' => ['nullable', 'string'],
        ]);

        $relationship = RelationshipType::tryFrom($data['relationship'] ?? 'parent_of') ?? RelationshipType::ParentOf;
        $result = $add->execute(
            TenantContext::requireSchoolId(),
            $family,
            $request->user()->person_id,
            $data,
            $relationship,
        );

        return response()->json([
            'adult' => [
                'id' => $result['adult']->id,
                'first_name' => $result['adult']->first_name,
                'last_name' => $result['adult']->last_name,
            ],
            'invitation_code' => $result['invitation_code'],
        ], 201);
    }

    public function invite(Request $request, string $school, string $family, ReissueParentInvitation $reissue): JsonResponse
    {
        $this->guardFamily($family);
        $data = $request->validate([
            'person_id' => ['required', 'uuid'],
        ]);

        $member = FamilyMember::query()
            ->where('family_id', $family)
            ->where('person_id', $data['person_id'])
            ->whereNull('left_at')
            ->first();
        if ($member === null) {
            throw new DomainException('Personne introuvable dans ce foyer.', 404);
        }

        $code = $reissue->execute(TenantContext::requireSchoolId(), $data['person_id'], $request->user()->person_id);

        return response()->json(['invitation_code' => $code]);
    }

    public function updateMember(Request $request, string $school, string $family, string $person, UpdatePersonCivilData $update): JsonResponse
    {
        $this->guardFamily($family);
        $member = FamilyMember::query()
            ->where('family_id', $family)
            ->where('person_id', $person)
            ->whereNull('left_at')
            ->first();
        if ($member === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $model = Person::query()->find($person);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'sex' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email'],
            'preferred_language' => ['sometimes', 'nullable', 'string', 'max:8'],
        ]);

        $update->execute($model, $data, $member->role_in_family === 'adult');
        $familyModel = Family::query()->find($family);
        if ($familyModel === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => FamilyPayload::forSchool($familyModel)]);
    }

    public function store(Request $request, CreateFamilyWithStudent $create, FindSimilarPersons $find): JsonResponse
    {
        $data = $request->validate([
            'school_year_id' => ['required', 'uuid'],
            'parent' => ['required', 'array'],
            'parent.first_name' => ['required', 'string', 'max:120'],
            'parent.last_name' => ['required', 'string', 'max:120'],
            'parent.birth_date' => ['nullable', 'date'],
            'parent.sex' => ['nullable', 'string'],
            'parent.phone' => ['nullable', 'string'],
            'parent.email' => ['nullable', 'email'],
            'student' => ['required', 'array'],
            'student.first_name' => ['required', 'string', 'max:120'],
            'student.last_name' => ['required', 'string', 'max:120'],
            'student.birth_date' => ['nullable', 'date'],
            'student.sex' => ['nullable', 'string'],
            'relationship' => ['nullable', 'string'],
            'student_number' => ['nullable', 'string', 'max:32'],
            'label' => ['nullable', 'string', 'max:120'],
            'classroom_id' => ['nullable', 'uuid'],
        ]);

        $relationship = RelationshipType::tryFrom($data['relationship'] ?? 'parent_of') ?? RelationshipType::ParentOf;

        $result = $create->execute(
            schoolId: TenantContext::requireSchoolId(),
            schoolYearId: $data['school_year_id'],
            actorPersonId: $request->user()->person_id,
            parent: $data['parent'],
            student: $data['student'],
            relationship: $relationship,
            studentNumber: $data['student_number'] ?? null,
            familyLabel: $data['label'] ?? null,
            classroomId: $data['classroom_id'] ?? null,
        );

        $parentLink = SchoolPersonLink::query()
            ->where('person_id', $result['parent']->id)
            ->where('kind', 'parent')
            ->firstOrFail();
        $studentLink = SchoolPersonLink::query()
            ->where('person_id', $result['student']->id)
            ->where('kind', 'student')
            ->firstOrFail();

        $except = [$result['parent']->id, $result['student']->id];
        $warnings = [];
        foreach (array_merge(
            $find->inSchool(TenantContext::requireSchoolId(), $data['parent'], $except),
            $find->inSchool(TenantContext::requireSchoolId(), $data['student'], $except),
        ) as $row) {
            $warnings[$row['id']] = $row;
        }

        return response()->json([
            'family_id' => $result['family']->id,
            'family_label' => $result['family']->label,
            'invitation_code' => $result['invitation_code'],
            'parent' => PersonPayload::forSchool($result['parent'], $parentLink),
            'student' => PersonPayload::forSchool($result['student'], $studentLink),
            'enrollment_id' => $result['enrollment']->id,
            'classroom_id' => $result['enrollment']->classroom_id,
            'warnings' => array_values($warnings),
        ], 201);
    }

    private function guardFamily(string $familyId): void
    {
        if (! FamilyHasSchoolEnrollment::exists($familyId)) {
            abort(response()->json(['message' => 'Not found.'], 404));
        }
    }
}
