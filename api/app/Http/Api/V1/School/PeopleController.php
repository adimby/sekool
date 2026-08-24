<?php

namespace App\Http\Api\V1\School;

use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Identity\Support\PersonPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class PeopleController extends Controller
{
    public function index(): JsonResponse
    {
        $links = SchoolPersonLink::query()->with('person')->orderBy('established_at')->get();

        return response()->json([
            'data' => $links->map(fn (SchoolPersonLink $link) => PersonPayload::forSchool($link->person, $link))->values(),
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
}
