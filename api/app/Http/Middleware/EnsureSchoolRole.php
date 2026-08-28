<?php

namespace App\Http\Middleware;

use App\Domain\School\Support\SchoolGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSchoolRole
{
    public function handle(Request $request, Closure $next, string $group): Response
    {
        SchoolGate::assertGroup($request, $group);

        return $next($request);
    }
}
