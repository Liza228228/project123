<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsBoilerChief
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasAnyRoleId(User::REPORT_GENERATOR_ROLE_IDS)) {
            abort(403, 'Раздел доступен только начальнику котельной и администратору.');
        }

        return $next($request);
    }
}
