<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\SchoolRoleAssignment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof UserAccount) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $schoolId = $request->route('school')
            ?? $request->headers->get('X-School-Id');

        if (is_object($schoolId) && isset($schoolId->id)) {
            $schoolId = $schoolId->id;
        }

        if (! is_string($schoolId) || $schoolId === '') {
            return response()->json(['message' => 'School context is required.'], 400);
        }

        TenantContext::activate(TenantContext::identifiedPerson($user->person_id));

        $hasRole = SchoolRoleAssignment::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('person_id', $user->person_id)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasRole) {
            TenantContext::clear();

            return response()->json(['message' => 'Not found.'], 404);
        }

        TenantContext::activate(TenantContext::forSchool($schoolId, $user->person_id));

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
