<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Authorize the request when the user holds at least one of the given
     * permissions. Super Admin bypasses every check.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->hasAnyPermission($permissions),
            403,
        );

        return $next($request);
    }
}
