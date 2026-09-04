<?php

namespace App\Http\Api\V1\Platform;

use App\Domain\School\Actions\GrantSchoolAdminFromPlatform;
use App\Domain\School\Actions\ProvisionSchoolFromPlatform;
use App\Domain\School\Actions\UpdateSchoolDirectoryFromPlatform;
use App\Domain\School\Models\School;
use App\Domain\School\Support\SchoolDirectoryPayload;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlatformSchoolController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = School::query()->orderBy('name')->get();

        return response()->json([
            'data' => $rows->map(fn (School $school): array => SchoolDirectoryPayload::for($school))->values(),
        ]);
    }

    public function store(Request $request, ProvisionSchoolFromPlatform $provision): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'short_name' => ['nullable', 'string', 'max:32'],
            'code' => ['nullable', 'string', 'max:32', 'alpha_dash', 'unique:schools,code'],
            'city' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'phone_e164' => ['nullable', 'string', 'max:24'],
            'email' => ['nullable', 'email', 'max:160'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:10'],
            'plan' => ['nullable', Rule::in(['starter', 'plus'])],
            'admin' => ['nullable', 'array'],
            'admin.first_name' => ['required_with:admin', 'string', 'max:80'],
            'admin.last_name' => ['required_with:admin', 'string', 'max:80'],
            'admin.email' => ['required_with:admin', 'email', 'max:160'],
            'admin.password' => ['nullable', 'string', 'min:8', 'max:80'],
        ]);

        $admin = isset($data['admin']) ? [
            'first_name' => $data['admin']['first_name'],
            'last_name' => $data['admin']['last_name'],
            'email' => $data['admin']['email'],
            ...isset($data['admin']['password']) ? ['password' => $data['admin']['password']] : [],
        ] : null;

        unset($data['admin']);
        $result = $provision->execute($data, $admin);
        $payload = SchoolDirectoryPayload::for($result['school']);

        if (isset($result['temporary_password'])) {
            $payload['temporary_password'] = $result['temporary_password'];
        }

        return response()->json(['data' => $payload], 201);
    }

    public function show(string $school): JsonResponse
    {
        $model = School::query()->find($school);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => SchoolDirectoryPayload::for($model)]);
    }

    public function update(Request $request, string $school, UpdateSchoolDirectoryFromPlatform $update): JsonResponse
    {
        $model = School::query()->find($school);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:32'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'region' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone_e164' => ['sometimes', 'nullable', 'string', 'max:24'],
            'email' => ['sometimes', 'nullable', 'email', 'max:160'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
            'plan' => ['sometimes', Rule::in(['starter', 'plus'])],
            'reason' => ['required_with:status,plan', 'nullable', 'string', 'min:12', 'max:500'],
        ], [
            'reason.required_with' => 'Un motif est obligatoire pour changer le statut ou le plan.',
            'reason.min' => 'Le motif doit expliquer le changement (au moins 12 caractères).',
        ]);

        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        $model = $update->execute($model, $data, is_string($reason) ? $reason : null);

        return response()->json(['data' => SchoolDirectoryPayload::for($model)]);
    }

    public function storeAdmin(Request $request, string $school, GrantSchoolAdminFromPlatform $grant): JsonResponse
    {
        $model = School::query()->find($school);
        if ($model === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:160'],
            'password' => ['nullable', 'string', 'min:8', 'max:80'],
        ]);

        $granted = $grant->execute($model, $data);
        $payload = SchoolDirectoryPayload::for($model->refresh());

        if (isset($granted['temporary_password'])) {
            $payload['temporary_password'] = $granted['temporary_password'];
        }

        return response()->json(['data' => $payload], 201);
    }
}
