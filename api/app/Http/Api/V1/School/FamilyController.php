<?php

namespace App\Http\Api\V1\School;

use App\Domain\Identity\Actions\CreateFamilyWithStudent;
use App\Domain\Identity\Enums\RelationshipType;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Identity\Support\PersonPayload;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FamilyController extends Controller
{
    public function store(Request $request, CreateFamilyWithStudent $create): JsonResponse
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
        );

        $parentLink = SchoolPersonLink::query()
            ->where('person_id', $result['parent']->id)
            ->where('kind', 'parent')
            ->firstOrFail();
        $studentLink = SchoolPersonLink::query()
            ->where('person_id', $result['student']->id)
            ->where('kind', 'student')
            ->firstOrFail();

        return response()->json([
            'family_id' => $result['family']->id,
            'invitation_code' => $result['invitation_code'],
            'parent' => PersonPayload::forSchool($result['parent'], $parentLink),
            'student' => PersonPayload::forSchool($result['student'], $studentLink),
            'enrollment_id' => $result['enrollment']->id,
        ], 201);
    }
}
