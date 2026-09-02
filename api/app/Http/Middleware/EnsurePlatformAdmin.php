<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\UserAccount;
use App\Domain\Identity\Support\PrivilegedAccount;
use App\Domain\Platform\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof UserAccount || ! PrivilegedAccount::isPlatformAdmin($user)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        TenantContext::activate(TenantContext::platformAdmin($user->person_id));

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
