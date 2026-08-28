<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetPersonContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof UserAccount) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        TenantContext::activate(TenantContext::identifiedPerson($user->person_id));

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
