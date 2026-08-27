<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Actions\UpdatePersonCivilData;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Identity\Support\PersonPayload;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChildrenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $parentId = $request->user()->person_id;
        $childIds = ParentAuthorization::accessibleChildIds($parentId);
        $guardianIds = ParentAuthorization::authorizedChildIds($parentId);

        $children = Person::query()->whereIn('id', $childIds)->get();

        $enrollments = TenantContext::runWithRlsBypass(fn () => Enrollment::query()
            ->withoutGlobalScopes()
            ->whereIn('person_id', $childIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('person_id'));

        $data = $children->map(function (Person $child) use ($enrollments, $guardianIds): array {
            $payload = PersonPayload::forParent($child);
            $payload['enrollments'] = ($enrollments[$child->id] ?? collect())->map(fn (Enrollment $row): array => [
                'id' => $row->id,
                'school_id' => $row->school_id,
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
            ])->values();
            $payload['access'] = in_array($child->id, $guardianIds, true) ? 'guardian' : 'finance';

            return $payload;
        })->values();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, string $person): JsonResponse
    {
        if (! ParentAuthorization::canSeeFinance($request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $child = Person::query()->find($person);
        if ($child === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $payload = PersonPayload::forParent($child);
        $payload['access'] = ParentAuthorization::isLegalGuardianOf($request->user()->person_id, $person)
            ? 'guardian'
            : 'finance';

        return response()->json(['data' => $payload]);
    }

    public function update(Request $request, string $person, UpdatePersonCivilData $update): JsonResponse
    {
        $parentId = $request->user()->person_id;
        if ($person !== $parentId && ! ParentAuthorization::isLegalGuardianOf($parentId, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $child = Person::query()->find($person);
        if ($child === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'sex' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email'],
        ]);

        $updated = $update->execute($child, $data, $person === $parentId);

        return response()->json(['data' => PersonPayload::forParent($updated)]);
    }
}
