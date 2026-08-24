<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Enrollment\Models\Enrollment;
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
        $childIds = ParentAuthorization::authorizedChildIds($parentId);

        $children = Person::query()->whereIn('id', $childIds)->get();

        $enrollments = TenantContext::runWithRlsBypass(fn () => Enrollment::query()
            ->withoutGlobalScopes()
            ->whereIn('person_id', $childIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('person_id'));

        $data = $children->map(function (Person $child) use ($enrollments): array {
            $payload = PersonPayload::forParent($child);
            $payload['enrollments'] = ($enrollments[$child->id] ?? collect())->values();

            return $payload;
        })->values();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, string $person): JsonResponse
    {
        if (! ParentAuthorization::isLegalGuardianOf($request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $child = Person::query()->find($person);
        if ($child === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => PersonPayload::forParent($child)]);
    }
}
