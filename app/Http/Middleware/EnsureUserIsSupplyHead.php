<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSupplyHead
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasRoleId(2)) {
            abort(403, 'Раздел доступен только начальнику отдела снабжения.');
        }

        return $next($request);
    }
}
