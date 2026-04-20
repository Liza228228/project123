<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsBoilerChief
{
    private const BOILER_CHIEF_ROLE_ID = 7;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            abort(403, 'Раздел доступен только начальнику котельной.');
        }

        return $next($request);
    }
}
