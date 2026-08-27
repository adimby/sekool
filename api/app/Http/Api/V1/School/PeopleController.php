<?php

namespace App\Http\Api\V1\School;

use App\Domain\Identity\Actions\UpdatePersonCivilData;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Identity\Support\PersonPayload;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\SchoolRoleAssignment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PeopleController extends Controller
{
    public function index(): JsonResponse
    {
        $links = SchoolPersonLink::query()->with('person')->orderBy('established_at')->get();

        return response()->json([
            'data' => $links->map(fn (SchoolPersonLink $link) => PersonPayload::forSchool($link->person, $link))->values(),
        ]);
    }

    public function staff(): JsonResponse
    {
        $personIds = SchoolRoleAssignment::query()
            ->whereNull('revoked_at')
            ->whereIn('role', [
                SchoolRole::Teacher,
                SchoolRole::Staff,
                SchoolRole::Principal,
                SchoolRole::Admin,
            ])
            ->pluck('person_id')
            ->unique();

        $people = Person::query()
            ->whereIn('id', $personIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'data' => $people->map(fn (Person $person): array => [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'kind' => 'staff',
            ])->values(),
        ]);
    }

    public function show(string $school, string $person): JsonResponse
    {
        $link = SchoolPersonLink::query()->where('person_id', $person)->first();

        if ($link === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $model = Person::query()->find($person);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => PersonPayload::forSchool($model, $link)]);
    }

    public function update(Request $request, string $school, string $person, UpdatePersonCivilData $update): JsonResponse
    {
        $link = SchoolPersonLink::query()->where('person_id', $person)->first();
        if ($link === null) {
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
            'birth_date_precision' => ['sometimes', 'nullable', 'string'],
            'sex' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email'],
            'preferred_language' => ['sometimes', 'nullable', 'string', 'max:8'],
        ]);

        $updated = $update->execute($model, $data, (bool) $link->grants_contact_access);

        return response()->json(['data' => PersonPayload::forSchool($updated, $link->refresh())]);
    }
}
